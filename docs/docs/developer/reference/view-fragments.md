---
title: View fragments (?view=…&fragment=…)
sidebar_position: 12
---

# View fragments

A request with `?view=<view>&fragment=<name>` renders **one region of a view** instead of the
whole page. It is the view-side counterpart of a `?call=` endpoint: the same view, the same
permission check, the same `processView()`, but no page chrome (`main.template.php`).

Datastar uses it to morph a region in place:

```html
<div id="mixed-search-results">…</div>
```

```html
<form method="get" class="homepage-search"
      data-on:input__debounce.100ms="… && @get('?fragment=results', {contentType: 'form'})">
```

Because the form is a plain GET form, the same request works without JavaScript (full page), and
Enter submits normally.

## Pieces

| Piece | Where | Role |
|---|---|---|
| `View::requestedFragment()` | `include/view.class.php` | The requested fragment name, or `NULL` for a full page. `processView()` uses it to skip full-page-only behaviour (e.g. `View__Mixed_Search` only redirects to a single result on a full page load). |
| `View::printFragment(string $name)` | `include/view.class.php` | Print the region. Default: `404` "Unknown fragment". Override per view, and fall back to `parent::printFragment($name)` for unknown names. |
| Dispatch | `include/system_controller.class.php` (`run()`) | Resolves the view from `$_SESSION['views']` and runs the permission check exactly as for a full page, then calls `printFragment()` instead of requiring the layout. |

## Rules

- **The fragment's top-level element carries the id that the full page also prints.** The
  fragment is the *same markup* the full page uses for that region, so there is one renderer. Give
  it a class constant on the view (`View__Mixed_Search::RESULTS_ID`) rather than repeating the
  string.
- **The morph target must not contain the input the user is typing in.** Print the form outside
  the region.
- **State changes stay on `?call=` with `@post`.** Fragment requests are reads (`@get`); the
  request-origin check does not cover GET (see [request-origin-protection](request-origin-protection.mdx)).
- **A fragment request must not redirect.** `redirect()` exits, and `fetch` follows a `302`
  silently, so Datastar would splice the target page's elements into the current page by id. Guard
  with `View::requestedFragment()` as `View__Mixed_Search` does.
- **Don't queue session messages in a fragment request.** Nothing in the fragment response prints
  the `add_message()` queue, so a message queued while rendering a fragment would appear on the
  user's *next* full page, detached from what caused it.
- **Unmorphed jQuery bindings.** Handlers attached directly to elements inside the region are lost
  when it is replaced. Bind those on `document` instead (`tb_lib.js` does this for
  `table.clickable-rows`).

## Datastar

`resources/js/datastar-1.0.4.min.js` is vendored (version in its header comment; do not edit) and is
**not** loaded globally: Datastar evaluates `data-*` attribute expressions as JavaScript, so loading
it on every page would turn any stored HTML that keeps `data-on:*` attributes into script. Each
view that uses it prints its own `<script type="module" src="…/resources/js/datastar-1.0.4.min.js">`
(see `View__Mixed_Search::printView()`). Fragment responses don't need it. It is not part of the
compiled `jethro-<version>.js` bundle.

## See also

- [status-panels](status-panels.mdx) — the `?call=` + id'd-HTML pattern fragments complement.
