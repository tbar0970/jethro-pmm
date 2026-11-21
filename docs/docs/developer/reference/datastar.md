---
title: Datastar loading
sidebar_position: 20
---

# Datastar loading

[Datastar](https://data-star.dev/) v1.0.4 is vendored as the ES module
`resources/js/datastar-1.0.4.min.js`. `templates/head.template.php` loads it
with a `<script type="module">` tag on **every page by default**, so any markup
can use Datastar attributes (`data-signals`, `data-bind`, `data-on:…`, …)
without first registering that it needs them.

A page that is known not to need Datastar can skip the download by setting
`$GLOBALS['datastarNeeded'] = FALSE` before the head template renders. Do this
in a view's `processView()`, which runs before the template, or just before a
template includes `head.template.php`. Leaving the flag unset, or setting it
to `TRUE`, loads Datastar. The high-traffic pages that opt out are the staff
and members login forms (`templates/login_form.template.php`,
`members/templates/login_form.template.php`) and the staff and members home
views (`views/view_1_home.class.php`, `members/views/view_1_home.class.php`).

`head.template.php` may set the flag back to `TRUE` for UI that appears on
every page, overriding a page's opt-out.

The flag is a global, not a template variable: templates are included from
inside `System_Controller::run()`, so views can't reach the template's local
scope. `View` deliberately has no method for this.

This works the same way in the staff area, the members portal (`members/`) and
the public area (`public/`).

An ES module is deferred, so where its `<script>` tag sits among the classic
scripts makes no difference. The tag goes right after the
`// ---- JAVASCRIPT ------ //` marker.
