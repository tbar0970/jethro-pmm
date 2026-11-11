import { execFileSync, spawnSync } from "node:child_process";
import type { SpawnSyncReturns } from "node:child_process";
import { mkdtempSync, rmSync, writeFileSync } from "node:fs";
import os from "node:os";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { test, expect } from "../fixtures.js";

/**
 * CLI person import, `scripts/import_persons.php --file`.
 *
 * Runs the real script against the shared functest database (`jethro_functest`)
 * via `--config=devbox.d/functest_jethro_server/conf.php`, then checks the rows
 * it created. Names carry a random tag so parallel workers and reruns stay
 * isolated; like import.spec.ts, rows are left in place for the next suite run
 * to recreate.
 */

const PROJECT_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../../..");
const SCRIPT = path.join(PROJECT_ROOT, "scripts/import_persons.php");
const FUNCTEST_CONF = path.join(PROJECT_ROOT, "devbox.d/functest_jethro_server/conf.php");
const HEADER = "family_name,last_name,first_name,congregation,status,age_bracket";

/** Random letters-only tag, unique per call, safe in person names. */
function uniqueTag(): string {
  let tag = "";
  for (let i = 0; i < 8; i++) tag += String.fromCharCode(97 + Math.floor(Math.random() * 26));
  return "Cli" + tag;
}

/** Query the functest database directly, as other specs do. */
function sql(statement: string): string {
  return execFileSync("mariadb", ["jethro_functest", "-sNBe", statement], { encoding: "utf8" }).trim();
}

/** Run the importer inside the checkout so `php` resolves from the devbox PATH. */
function runImport(args: string[], cwd = PROJECT_ROOT) {
  return spawnSync("php", [SCRIPT, ...args], { cwd, encoding: "utf8" });
}

test.describe("CLI import (--file)", () => {
  test("creates a person, family and group from a local CSV", () => {
    const family = uniqueTag();
    const group = `${family} group`;
    const dir = mkdtempSync(path.join(os.tmpdir(), "jethro-import-cli-"));
    const csv = path.join(dir, "persons.csv");
    writeFileSync(csv, [HEADER, `${family},${family},Cli,6pm,Core,Adult`].join("\n") + "\n");

    let result: SpawnSyncReturns<string>;
    try {
      result = runImport([
        `--file=${csv}`,
        `--config=${FUNCTEST_CONF}`,
        `--create-group=${group}`,
        "--match-existing",
        "--verbose",
      ]);
    } finally {
      rmSync(dir, { recursive: true, force: true });
    }

    expect(result.status).toBe(0);
    expect(result.stderr).not.toMatch(/Row \d+|rejected|Fatal error/);
    expect(result.stdout).toContain("New families to be created:");
    expect(result.stdout).toContain(`Cli ${family}`);
    expect(result.stdout).toContain("Imported: 1 new families (1 persons)");
    const groupId = Number(/Import complete\. Group id: (\d+)/.exec(result.stdout)?.[1]);
    expect(groupId).toBeGreaterThan(0);

    const person = sql(
      `SELECT p.id, f.family_name FROM _person p JOIN family f ON p.familyid = f.id ` +
        `WHERE p.first_name = 'Cli' AND p.last_name = '${family}'`,
    ).split("\t");
    expect(person).toHaveLength(2);
    expect(person[1]).toBe(family);
    expect(
      sql(`SELECT COUNT(*) FROM person_group_membership WHERE groupid = ${groupId} AND personid = ${person[0]}`),
    ).toBe("1");
  });

  test("keeps blank family_name cells in the family above (--file)", () => {
    const family = uniqueTag();
    const dir = mkdtempSync(path.join(os.tmpdir(), "jethro-import-cli-"));
    const csv = path.join(dir, "continuation.csv");
    writeFileSync(
      csv,
      [HEADER, `${family},${family},One,6pm,Core,Adult`, `,${family}Other,Two,6pm,Core,Adult`].join("\n") + "\n",
    );

    let result: SpawnSyncReturns<string>;
    try {
      result = runImport([
        `--file=${csv}`,
        `--config=${FUNCTEST_CONF}`,
        `--create-group=${family} group`,
        "--dry-run",
      ]);
    } finally {
      rmSync(dir, { recursive: true, force: true });
    }

    expect(result.status).toBe(0);
    expect(result.stdout).toContain("Would import: 1 new families (2 persons)");
    expect(result.stdout).toContain("Dry run; nothing written");
  });

  test("rejects an invalid row and writes nothing", () => {
    const family = uniqueTag();
    const group = `${family} group`;
    const dir = mkdtempSync(path.join(os.tmpdir(), "jethro-import-cli-"));
    const csv = path.join(dir, "bad.csv");
    // "Core" requires a congregation; leaving it blank is a row error.
    writeFileSync(csv, [HEADER, `${family},${family},Rejected,,Core,Adult`].join("\n") + "\n");

    let result: SpawnSyncReturns<string>;
    try {
      result = runImport([`--file=${csv}`, `--config=${FUNCTEST_CONF}`, `--create-group=${group}`]);
    } finally {
      rmSync(dir, { recursive: true, force: true });
    }

    expect(result.status).toBe(1);
    expect(result.stderr).toContain("Import rejected; nothing written");
    expect(result.stderr).toContain("cannot have a blank congregation");
    expect(sql(`SELECT COUNT(*) FROM _person WHERE last_name = '${family}'`)).toBe("0");
    expect(sql(`SELECT COUNT(*) FROM _person_group WHERE name = '${group}'`)).toBe("0");
  });
});
