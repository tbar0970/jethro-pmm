#!/usr/bin/env nu
# Functional tests for a Jethro site.
# Configuration is read from functests/credentials.json (base_url, username, password).
#
# Usage:
#   nu jethrotests.nu --saveall                 # Fetch all pages and save snapshots
#   nu jethrotests.nu --save login              # Fetch one page and save its snapshot
#   nu jethrotests.nu --testall                 # Test all pages against saved snapshots
#   nu jethrotests.nu --test login              # Test one page by id
#   nu jethrotests.nu --test "login,admin_home" # Test a comma-separated list of pages
#   nu jethrotests.nu --credentials /path/to/credentials.json --testall
def main [
    --saveall                    # Fetch all pages and save HTML snapshots to functests/saved/
    --save: string = ""          # Fetch one page by id and save its snapshot
    --testall                    # Test all pages against saved snapshots
    --test: string = ""          # Test a comma-separated list of page ids
    --show: string = ""          # Fetch a page by id and print its normalised JSON to stdout
    --credentials: string = ""   # Path to credentials.json (default: credentials.json next to this script)
] {
    if not $saveall and ($save | is-empty) and not $testall and ($test | is-empty) and ($show | is-empty) {
        nu $env.CURRENT_FILE --help
        return
    }

    let script_dir = $env.CURRENT_FILE | path dirname
    let credentials_path = if ($credentials | is-empty) {
        $script_dir | path join "credentials.json"
    } else {
        $credentials
    }
    let config = open $credentials_path
    let saved_dir = $script_dir | path join "saved"

    # -------------------------------------------------------------------------
    # Public (unauthenticated) pages
    # -------------------------------------------------------------------------
    let public_pages = [
        {id: "login", path: "/"}
        {id: "public_home", path: "/public/?view=home"}
        {id: "public_rosters", path: "/public/?view=rosters"}
        {id: "public_rosters_view_1", path: "/public/?view=rosters&roster_view=1"}
        {id: "public_rosters_view_2", path: "/public/?view=rosters&roster_view=2"}
        {id: "public_rosters_view_3", path: "/public/?view=rosters&roster_view=3"}
        {id: "public_roster_roles", path: "/public/?view=_roster_role_description"}
        {id: "public_roster_role_1", path: "/public/?view=_roster_role_description&role=1"}
        {id: "public_roster_role_2", path: "/public/?view=_roster_role_description&role=2"}
        {id: "public_roster_role_6", path: "/public/?view=_roster_role_description&role=6"}
        {id: "public_check_in", path: "/public/?view=check_in"}
    ]

    # -------------------------------------------------------------------------
    # Admin (authenticated) pages — require valid credentials in credentials.json
    # -------------------------------------------------------------------------
    let admin_pages = [
        {id: "home", path: "/?view=home"}
        {id: "families", path: "/?view=families"}
        {id: "families_list_all", path: "/?view=families__list_all"}
        {id: "families_contact_list", path: "/?view=families__contact_list"}
        {id: "persons", path: "/?view=persons"}
        {id: "persons_list_all", path: "/?view=persons__list_all"}
        {id: "persons_reports", path: "/?view=persons__reports"}
        {id: "persons_reports_configure_1", path: "/?view=persons__reports&queryid=1&configure=1"}
        {id: "persons_statistics", path: "/?view=persons__statistics"}
        {id: "notes_immediate", path: "/?view=notes__for_immediate_action"}
        {id: "notes_future", path: "/?view=notes__for_future_action"}
        {id: "groups", path: "/?view=groups"}
        {id: "groups_list_all", path: "/?view=groups__list_all"}
        {id: "groups_manage_categories", path: "/?view=groups__manage_categories"}
        {id: "attendance_record", path: "/?view=attendance__record"}
        {id: "attendance_display", path: "/?view=attendance__display"}
        {id: "attendance_statistics", path: "/?view=attendance__statistics"}
        {id: "attendance_checkins", path: "/?view=attendance__checkins"}
        {id: "rosters_display", path: "/?view=rosters__display_roster_assignments"}
        {id: "rosters_edit", path: "/?view=rosters__edit_roster_assignments"}
        {id: "rosters_define_roles", path: "/?view=rosters__define_roster_roles"}
        {id: "rosters_define_views", path: "/?view=rosters__define_roster_views"}
        {id: "services", path: "/?view=services"}
        {id: "services_list_all", path: "/?view=services__list_all"}
        {id: "services_component_library", path: "/?view=services__component_library"}
        {id: "services_reporting", path: "/?view=services__reporting"}
        {id: "documents", path: "/?view=documents"}
        {id: "congregations", path: "/?view=admin__congregations"}
        {id: "user_accounts", path: "/?view=admin__user_accounts"}
        {id: "custom_fields", path: "/?view=admin__custom_fields"}
        {id: "note_templates", path: "/?view=admin__note_templates"}
        {id: "system_configuration", path: "/?view=admin__system_configuration"}
        {id: "action_plans", path: "/?view=admin__action_plans"}
        {id: "import", path: "/?view=admin__import"}
        {id: "person_photo_169", path: "/?call=photo&personid=169"}
        {id: "person_photo_168", path: "/?call=photo&personid=168"}
        {id: "person_168", path: "/?view=persons&personid=168"}
        {id: "person_170", path: "/?view=persons&personid=170"}
        {id: "person_171", path: "/?view=persons&personid=171"}
        {id: "family_76", path: "/?view=families&familyid=76"}
        {id: "family_66", path: "/?view=families&familyid=66"}
        {id: "family_58", path: "/?view=families&familyid=58"}
        {id: "group_1", path: "/?view=groups&groupid=1"}
        {id: "group_3", path: "/?view=groups&groupid=3"}
        {id: "group_4", path: "/?view=groups&groupid=4"}
        {id: "service_20260417_cong2", path: "/?view=services&date=2026-04-17&congregationid=2"}
        {id: "service_20260419_cong2", path: "/?view=services&date=2026-04-19&congregationid=2"}
        {id: "service_20260426_cong2", path: "/?view=services&date=2026-04-26&congregationid=2"}
        {id: "service_comp_detail_145", path: "/?call=service_comp_detail&id=145"}
        {id: "edit_service_component_251", path: "/?id=251&view=_edit_service_component&service_componentid=251"}
    ]

    if not ($show | is-empty) {
        let all_pages = $public_pages | append $admin_pages
        let matches = $all_pages | where id == $show
        if ($matches | is-empty) {
            error make {msg: $"Unknown test id: ($show)"}
        }
        let page = $matches | first
        let cookie = if ($page.id | str starts-with "public") or $page.id == "login" { "" } else { do-login $config }
        fetch-page ($config.base_url + $page.path) $cookie | print
        return
    }

    if not ($save | is-empty) {
        mkdir $saved_dir
        let all_pages = $public_pages | append $admin_pages
        let matches = $all_pages | where id == $save
        if ($matches | is-empty) {
            error make {msg: $"Unknown test id: ($save)"}
        }
        let page = $matches | first
        let cookie = if ($page.id | str starts-with "public") or $page.id == "login" { "" } else {
            if ($config.username | is-empty) or ($config.password | is-empty) {
                error make {msg: "Admin pages require credentials in credentials.json"}
            }
            do-login $config
        }
        run-save $page $config.base_url $saved_dir $cookie
    }

    if $saveall {
        rm -rf $saved_dir
        mkdir $saved_dir

        # Public pages
        print "== Public pages =="
        $public_pages | par-each {|page| run-save $page $config.base_url $saved_dir ""}

        # Admin pages
        if ($config.username | is-empty) or ($config.password | is-empty) {
            print "\nSkipping admin pages: no credentials in credentials.json"
        } else {
            print "\n== Admin pages =="
            let cookie = do-login $config
            $admin_pages | par-each {|page| run-save $page $config.base_url $saved_dir $cookie}
        }

        print "\nDone."
    }

    if $testall or (not ($test | is-empty)) {
        # Determine which pages to run
        let all_pages = $public_pages | append $admin_pages
        let pages_to_run = if not ($test | is-empty) {
            let ids = $test | split row "," | each { str trim }
            let found = $all_pages | where {|p| $p.id in $ids }
            let unknown = $ids | where {|id| not ($id in ($all_pages | get id)) }
            if not ($unknown | is-empty) {
                error make {msg: $"Unknown test id\(s): ($unknown | str join ', ')"}
            }
            $found
        } else {
            $all_pages
        }

        let needs_auth = (
            $pages_to_run
            | where {|p| not ($p.id | str starts-with "public") and $p.id != "login"}
            | length
        ) > 0
        let cookie = if $needs_auth {
            if ($config.username | is-empty) or ($config.password | is-empty) {
                error make {msg: "Admin pages require credentials in credentials.json"}
            }
            do-login $config
        } else {
            ""
        }

        let failures = $pages_to_run | par-each {|page|
            let page_cookie = if ($page.id | str starts-with "public") or $page.id == "login" { "" } else { $cookie }
            {id: $page.id, result: (run-test $page $config.base_url $saved_dir $page_cookie)}
        } | where result == "fail" | get id

        if ($failures | length) > 0 {
            print $"\n($failures | length) test\(s) failed: ($failures | str join ', ')"
            exit 1
        } else {
            print "\nAll tests passed."
        }
    }
}

# Log in and return the authenticated session cookie string
export def do-login [config: record]: nothing -> string {
    # Step 1: GET the login page to obtain an initial session cookie and the CSRF login_key
    let get_resp = http get --full $config.base_url
    let init_cookie = $get_resp.headers.response
    | where name == "set-cookie"
    | get value
    | first
    | split row ";"
    | first
    let login_key = $get_resp.body
    | parse --regex `name="login_key" value="(?P<key>[^"]+)"`
    | get key
    | first

    let post_body = {username: $config.username, password: $config.password, login_key: $login_key}
    let post_resp = (
        http post --redirect-mode manual --full --headers {Cookie: $init_cookie} --content-type application/x-www-form-urlencoded $config.base_url $post_body
    )

    if $post_resp.status != 302 {
        error make {msg: $"Login failed \(HTTP ($post_resp.status)\). Check credentials in credentials.json. Output is saved in /tmp/login_page.html"}
    }

    let auth_cookie = $post_resp.headers.response
    | where name == "set-cookie"
    | get value
    | first
    | split row ";"
    | first

    $auth_cookie
}

def normalize-html []: string -> string {
    lines
    | each { str trim }
    | where {|l| ($l | str length) > 0 }
    | str join "\n"
}

def fetch-resp [url: string, cookie: string] {
    if ($cookie | is-empty) {
        http get --full --allow-errors $url
    } else {
        http get --full --allow-errors --headers {Cookie: $cookie} $url
    }
}

def normalize-html-body []: string -> string {
    str replace --regex `<meta name="jethro-request-id"[^/]*/>\n?` ''
    | str replace --all --regex `\?t=\d+` ''
    | str replace --regex `name="login_key" value="[^"]*"` 'name="login_key" value=""'
    | normalize-html
}

def ct-to-vim-ft [ct: string]: nothing -> string {
    let mime = $ct | split row ";" | first | str trim # e.g. strip the charset from "text/html; charset=UTF-8"
    match $mime {
        "text/html"              => "html"
        "application/json"       => "json"
        "text/css"               => "css"
        "text/javascript"        => "javascript"
        "application/javascript" => "javascript"
        "text/plain"             => "text"
        _                        => ""
    }
}

def build-saved-content [resp: record, body]: nothing -> binary {
    let ct = $resp.headers.response | where name == "content-type" | get value | first
    let ft = ct-to-vim-ft $ct
    let modeline = if ($ft | is-empty) { "" } else { $"# vim: ft=($ft)\n" }
    let header = ( [modeline
                    $"X-Status-Code: ($resp.status)"
                    $"Content-Type: ($ct)"] | str join "\n"
                 ) + "\n"
    let body_bytes = if ($body | describe) == "binary" { $body } else {
        $body | into binary
    }
    ($header | into binary) ++ $body_bytes
}

export def fetch-page [url: string, cookie: string] {
    (fetch-resp $url $cookie).body | normalize-html-body
}

export def run-save [
    page: record
    base_url: string
    saved_dir: string
    cookie: string
] {
    let url = $base_url + $page.path
    let out_path = $saved_dir | path join $page.id
    print $"  Saving ($page.id): ($url)"
    let resp = fetch-resp $url $cookie
    let body = if ($resp.body | describe) == "binary" { $resp.body } else {
        $resp.body | normalize-html-body
    }
    build-saved-content $resp $body | save --force $out_path
    let label = match ($resp.status // 100) {
        2 => "Saved"
        3 => $"(ansi yellow)Saved \(redirect\)(ansi reset)"
        _ => $"(ansi red)Saved \(error\)(ansi reset)"
    }
    print $"    ($label) -> ($out_path)"
}

export def run-test [
    page: record
    base_url: string
    saved_dir: string
    cookie: string
]: nothing -> string {
    let url = $base_url + $page.path
    let saved_path = $saved_dir | path join $page.id

    if not ($saved_path | path exists) {
        print $"  SKIP  ($page.id): no snapshot, run --saveall or --save ($page.id) first"
        return "skip"
    }

    let resp = fetch-resp $url $cookie
    let body = if ($resp.body | describe) == "binary" { $resp.body } else {
        $resp.body | normalize-html-body
    }
    let actual = build-saved-content $resp $body
    let expected_raw = open --raw $saved_path
    let expected = if ($expected_raw | describe) == "binary" { $expected_raw } else {
        $expected_raw | into binary
    }

    if $actual == $expected {
        let pass = if ($resp.status // 100) == 2 { "PASS" } else { $"(ansi red)PASS(ansi reset)" }
        print $"  ($pass)  ($page.id)"
        "pass"
    } else {
        let actual_path = $saved_dir | path join $"($page.id).actual"
        $actual | save --force $actual_path
        print $"  (ansi red)FAIL(ansi reset)  ($page.id): response differs from saved snapshot"
        print $"        vimdiff ($saved_path) ($actual_path)"
        "fail"
    }
}

export def fetch-binary [url: string, cookie: string] {
    let resp = fetch-resp $url $cookie
    {
        status: $resp.status
        hash: ($resp.body | hash sha256)
    }
}

# Fetch only the HTTP status code — useful for known-broken endpoints
export def fetch-status [url: string, cookie: string] {
    let resp = fetch-resp $url $cookie
    {status: $resp.status}
}
