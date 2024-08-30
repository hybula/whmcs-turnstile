# WHMCS Turnstile Captcha
Enables Cloudflare's [Turnstile](https://www.cloudflare.com/products/turnstile/) Captcha service in abandonware WHMCS. *This is currently a proof-of-concept, please report issues.*

![](https://github.com/hybula/whmcs-turnstile/assets/8611981/a4a11d07-ecaa-4f98-b461-13534222fd35)

### Introduction
By default WHMCS offers two types of captchas, the built-in-easily-cracked GD based captcha and the easily-cracked-privacy-violating reCAPTCHA by Google.
Because WHMCS fails to maintain their product, we developed this simple to use hook which enables Turnstile while completely bypassing WHMCS' logic.

Please note that this implementation required some filthy JS query code to make it work, because WHMCS is a complete mess: in some pages they used HTML buttons for forms, on other pages they used input submits, with or without IDs, inside divs, without divs, no use of IDs. Meaning that there was no streamlined way to do this clean and proper. Here are some awkward examples:
```HTML
<input class="btn btn-lg btn-primary" type="submit" value="Register">
<button id="login" type="submit" class="btn btn-primary">Login</button>
<button type="submit" name="validatepromo" class="btn btn-block btn-default" value="Validate Code">Validate Code</button>
<button type="submit" class="btn btn-primary">Send Message</button>
<a href="/cart.php?a=checkout&amp;e=false" class="btn btn-success btn-lg btn-checkout disabled" id="checkout">Checkout</a>
```

### Features
- Enables Turnstile captcha on login, register, checkout, ticket, contact pages.
- Support for themes (auto/dark/light).
- Ability to disable credits and have it fully white labeled.
- Ability to exclude captcha when client is logged in.

### Requirements
- PHP 8.x (tested on 8.1.27)
- WHMCS 8.x (tested on 8.9.0)

### Installation
1. Download the latest release and unzip it in the root of your WHMCS installation, make sure the hook file is placed in `includes/hooks`.
2. Get your Turnstile Site Key and Secret Key from your Cloudflare dashboard.
3. Edit and add the following settings in your `configuration.php`:
```php
const hybulaTurnstileEnabled = true;
const hybulaTurnstileCredits = true;
const hybulaTurnstileExcludeLogin = true;
const hybulaTurnstileSite = '';
const hybulaTurnstileSecret = '';
const hybulaTurnstileTheme = 'auto';
const hybulaTurnstileError = 'Something went wrong with your captcha challenge!';
const hybulaTurnstileLocations = ['login', 'register', 'checkout', 'ticket', 'contact'];
```

### Contribute
Contributions are welcome in a form of a pull request (PR).

### Sponsored
This project is developed and sponsored by [Hybula B.V.](https://www.hybula.com/)
<p>
  <a href="https://www.hybula.com/">
    <img src="https://www.hybula.com/assets/hybula/logo/logo-primary.svg" height="40px">
  </a>
</p>

### License
```Apache License, Version 2.0 and the Commons Clause Restriction```
