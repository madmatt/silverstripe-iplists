# IP allow/deny lists for SilverStripe

This module provides the capability for administrators to define IP allow and deny lists, colloquially known as IP whitelist and blacklists.

## Installation
```
composer require madmatt/silverstripe-iplists
vendor/bin/sake dev/build flush=1
```

Visit `/admin/iplists` to define the allow and deny lists that you want.

## When *not* to use this module
It's important to spell out when it's not a good idea to use this module. Specifically, it is not recommended to use this module to block IP addresses that are performing denial of service attacks on your website. This module hooks into Silverstripe CMS, meaning that the whole CMS and framework must boot before checking whether the IP address is allowed to access the website or not (so the website does 70% of the work it would do anyway). A much better way to block these attackers is to use a web application firewall such as Cloudflare, and block the offending IPs from accessing the entire website there. 

## Configuration
By default, this module does not do anything beyond adding a new middleware into every request. This middleware does nothing on CLI. To have this module be useful, you need to configure one or more IP lists. Each IP list can contain multiple URI location rules, as well as both allow and deny rules to determine who can access the URI location rules you specify.

You can configure lists in two different ways: in the CMS, and with developer-controlled YML files.

### CMS configuration

Documentation TBC once feature complete.

### YML configuration
**Note:** YML configuration is not implemented yet. Use CMS configuration for now. This configuration API is likely to change, please don't trust the below.

The intention with YML configuration is that these IP addresses are never (or 'very rarely') expected to change. For example, add the IP address of your office VPN here, but don't add your home IP - use the CMS interface for this so you can change it easily later.

The below YML config fragment will allow `127.0.0.1` and `10.0.0.2` access to login and view the SilverStripe CMS, and will deny `10.0.0.1` from viewing the website at all (despite the IP being in the allow list for the CMS, 

```yml
Madmatt\IPLists\Model\IPList:
  admin_allowlist:
    routes:
      - /admin
      - /Security
    allow:
      - 127.0.0.1
      - 10.0.0.1
      - 10.0.0.2
  wholesite_deny:
    routes:
      - /
    deny:
      - 10.0.0.1
```


## Why 'allow' and 'deny' instead of 'whitelist' and 'blacklist'?
1. Allow and Deny are more accurate terms than white and black.
2. Allow and Deny aren't racist terms.

See also:
1. https://www.clockwork.com/news/creating-inclusive-naming-conventions-in-technology/
2. https://www.theregister.co.uk/2019/09/03/chromium_microsoft_offensive/
3. https://twitter.com/andrestaltz/status/1030200563802230786
4. https://github.com/rails/rails/issues/33677
