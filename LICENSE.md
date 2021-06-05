## XLogin License ##

*Copyright (c) 2019-2020 Patrick Lai*

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 or later 3 of the
License, at your option.

This program includes various thirdparty software components:

* Composer - Dependency Management for PHP
  - https://github.com/composer/composer
  - files: vendor/composer/...
  - license: vendor/composer/LICENSE

* Guzzle, PHP HTTP Client
  - https://github.com/guzzle/guzzle
  - files: vendor/guzzlehttp/...
  - license: vendor/guzzlehttp/guzzle/LICENSE,
    vendor/guzzlehttp/promises/LICENSE, vendor/guzzlehttp/psr7/LICENSE

* OAuth 2.0 Client
  - https://github.com/thephpleague/oauth2-client
  - files: vendor/league/oauth2-client/...
  - license: vendor/league/oauth2-client/LICENSE

* Facebook Provider for OAuth 2.0 Client
  - https://github.com/thephpleague/oauth2-facebook
  - files: vendor/league/oauth2-facebook/...
  - license: vendor/league/oauth2-facebook/LICENSE

* Google Provider for OAuth 2.0 Client
  - https://github.com/thephpleague/oauth2-google
  - files: vendor/league/oauth2-google/...
  - license: vendor/league/oauth2-google/LICENSE

* random_compat
  - https://github.com/paragonie/random_compat
  - files: vendor/paragonie/random_compat/...
  - license: vendor/paragonie/random_compat/LICENSE

* PSR Http Message
  - https://github.com/php-fig/http-message
  - files: vendor/psr/http-message/...
  - license: vendor/psr/http-message/LICENSE

* getallheaders
  - https://github.com/ralouphie/getallheaders
  - files: vendor/ralouphie/getallheaders/...
  - license: vendor/ralouphie/getallheaders/LICENSE

* Vue.js
  - https://cdn.jsdelivr.net/npm/vue@2.6.13
  - license: MIT License

Some image files are also incorporated:

* Facebook icon(s)
  - https://en.facebookbrand.com/wp-content/uploads/2019/04/f-Logos-2019-1.zip
  - file: images/facebook/btn-signin.png
    (from *f_logo_online_04_2019/color/PNG/f_logo_RGB-Blue_144.png*)

* Google icon(s)
  - https://developers.google.com/identity/images/signin-assets.zip
  - files:
    - images/google/btn-signin.svg
      (from *google_signin_buttons/web/vector/btn_google_dark_normal_ios.svg*)
    - images/google/btn-signin.png
      (from *google_signin_buttons/ios/2x/btn_google_dark_normal_ios@2x.svg*)

* Yahoo icon(s)
  - http://www.iconarchive.com/show/simple-icons-by-danleech/yahoo-icon.html
  - http://icons.iconarchive.com/icons/danleech/simple/128/yahoo-icon.png
  - file: images/yahoo/btn-signin.png

At run-time, this program renders HTML elements that reference the
following web resources:

* Google 'Roboto' font
  - https://fonts.googleapis.com/css?family=Roboto
  - license: Apache License 2.0
