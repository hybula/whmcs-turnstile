# Turnstile Captcha

A WHMCS addon module that protects client area forms with a [Cloudflare Turnstile](https://developers.cloudflare.com/turnstile/) challenge.

It is unbranded: nothing is added to the rendered page beyond the challenge widget itself, and there are no credit links or outbound references to the module author.

## Features

- Challenges the login, registration, password reset, contact, ticket and checkout forms, each individually switchable.
- Configured entirely from the WHMCS admin interface. No edits to `configuration.php`.
- Uses the native WHMCS validation hooks where they exist, so rejections appear in the theme's own error area instead of replacing the page.
- Optional skipping of visitors who are already signed in.
- Configurable behaviour when Cloudflare is unreachable: reject (default) or allow.
- Built in connectivity and secret key self test on the module's admin page.
- Rejections are recorded in the activity log, with the secret key masked in module logs.

## Requirements

- WHMCS 8.x
- PHP 8.0 or newer
- The `curl` and `json` PHP extensions

## Installation

1. Copy the `modules/addons/turnstile` directory into the root of your WHMCS installation, preserving the path.
2. Create a Turnstile widget in the Cloudflare dashboard and note the site key and secret key.
3. In WHMCS, go to **System Settings → Addon Modules**, activate **Turnstile Captcha**, and grant access to the relevant admin roles.
4. Open the module's configuration, paste the keys, and enable the forms you want to protect.
5. Open the module (**Addons → Turnstile Captcha**) to confirm the self test reports that the endpoint is reachable and the secret key is accepted.

Test the result in a private browsing window. The challenge is skipped for signed in clients when **Skip Signed In Clients** is enabled.

## Settings

| Setting | Purpose |
| --- | --- |
| Site Key | Public key rendered into the page. |
| Secret Key | Private key used for server side verification only. |
| Widget Theme | `auto`, `light` or `dark`. |
| Widget Size | `normal`, `flexible` or `compact`. |
| Rejection Message | Text shown to a visitor whose challenge was not accepted. |
| Protect: … | One switch per form. |
| Skip Signed In Clients | Do not challenge an authenticated session. |
| Send Visitor IP | Include the connecting address in the verification call. Turn this off if the web server sees a proxy address rather than the real client. |
| Allow On Outage | Accept submissions when the verification endpoint cannot be reached. Off by default. |
| Log Rejections | Write rejected challenges to the activity log. |

## How it works

Registration, ticket submission and checkout are validated through the WHMCS hook points intended for the purpose, so failures are returned as normal form errors:

| Form | Hook |
| --- | --- |
| Registration | `ClientDetailsValidation` |
| Ticket submission | `TicketOpenValidation` |
| Cart checkout | `ShoppingCartValidateCheckout` |

WHMCS provides no validation hook for login, password reset or the contact form. Those submissions are checked while the module's hook file loads, before the page logic runs. A rejected submission is redirected back to the originating page with a message rather than terminating the response, and the redirect target is restricted to this installation's own host.

The widget is mounted by a static script in `assets/`, using Turnstile's explicit rendering mode. Configuration reaches it through an escaped data attribute, so no inline script is emitted and the widget is only ever attached to the form being protected.

## Notes

- Verification failures caused by an internal error in the module are logged and allowed through, so a fault here cannot take a store offline. Cloudflare outages are governed by **Allow On Outage** instead.
- If your theme uses heavily customised markup, the script falls back to the form with the most fields on the page when its selectors do not match.
