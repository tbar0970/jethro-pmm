/**
 * Provider-neutral helpers for SMS scenarios that run against more than one
 * mock provider. A scenario project's name is its mock profile name and its
 * `metadata.smsProvider` the provider its .conf configures (playwright.config.ts).
 */
import type { TestInfo } from "@playwright/test";
import { expect } from "../fixtures.js";

export type ScenarioProvider = "cellcast" | "5centsmsv5" | "mobilemessage";

/** The SMS provider the running scenario project is configured for. */
export function scenarioProvider(info: TestInfo): ScenarioProvider {
  const provider = info.project.metadata?.smsProvider;
  if (provider === undefined) {
    throw new Error(`Project ${info.project.name} sets no metadata.smsProvider (playwright.config.ts)`);
  }
  return provider;
}

/** The running scenario's mock profile, for mockMeta(). */
export function mockProfile(info: TestInfo): string {
  return `tests/functional/sms/${info.project.name}`;
}

/** A send request as the mock captured it (meta/lastPost), in provider-neutral form. */
export interface SentSms {
  message: string;
  /** Local form, e.g. "0491570158". */
  recipients: string[];
  sender: string | undefined;
  /** Requested send time in epoch ms; null for an immediate send. */
  scheduledAt: number | null;
}

/** Parse a captured send body (meta/lastPost `.json`) for the scenario's provider. */
export function sentSms(info: TestInfo, body: Record<string, any>): SentSms {
  let numbers: string[];
  let message: string;
  let sender: string | undefined;
  let scheduledAt: number | null;
  switch (scenarioProvider(info)) {
    case "cellcast":
      // Local numbers plus a country code; scheduleAt is UTC "Y-m-d H:i:s".
      expect(body.countryCode, "Cellcast sends country code 61 with local numbers").toBe(61);
      numbers = body.contacts;
      ({ message, sender } = body);
      scheduledAt = body.scheduleAt === undefined ? null : new Date(body.scheduleAt + "Z").getTime();
      break;
    case "5centsmsv5":
      // One comma-separated `to`; `schedule` is Unix seconds, omitted for an immediate send.
      numbers = String(body.to).split(",");
      ({ message, sender } = body);
      scheduledAt = body.schedule === undefined ? null : body.schedule * 1000;
      break;
    case "mobilemessage": {
      // One MessageInput per recipient, each carrying the text, sender and
      // an ISO `scheduled_for` (omitted for an immediate send).
      const inputs: Record<string, any>[] = body.messages;
      numbers = inputs.map((m) => m.to);
      ({ message, sender } = inputs[0]);
      scheduledAt = inputs[0].scheduled_for === undefined ? null : Date.parse(inputs[0].scheduled_for);
      break;
    }
  }
  return {
    message,
    // Australian mobiles in local form, whichever form the provider was sent.
    recipients: numbers.map((n) => n.replace(/^\+?61(?=4\d{8}$)/, "0")),
    sender,
    scheduledAt,
  };
}
