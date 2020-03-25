## Contributing to XLogin ##

Here are some suggested areas:

* *Testing with various WordPress versions.* The plugin was developed
  on WordPress 5.3. It has not been tested with other versions.

* *WordPress profile override.* The plugin has the option to override
  user information with profile data from external services. This is
  done in `XLogin::importXUser()`, where external data is injected
  into various WordPress global variables. There may be more proper
  and/or robust mechanisms in WordPress than messing with global
  variables.

* *Additional external services.* OAuth2 based services supported by
  [OAuth 2 Client](https://oauth2-client.thephpleague.com/) should
  be straightforward to add. Others that would require custom
  authentication pages include:
  - Login code by SMS
  - Login link by email
  - HTTP authorization (e.g. Apache basic/digest auth)

* *Localization and translation.* Server side code is utilizing
  WordPress `__()` function, but there are some subtle areas
  (e.g. generating display name from family and given names).
  Look for "TODO" in the code. Client HTML/Javascript items are
  generally lacking localization.

* *Keeping pace with WordPress.* This *is* a WordPress plugin, and it
  takes effort to keep up to date as WordPress develops. For example,
  this plugin serves its own copy of Vue.js because currently it is
  not a default library included with WordPress. That may change in
  the future.
