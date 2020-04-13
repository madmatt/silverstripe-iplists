# IP allow/deny lists for SilverStripe

This module provides the capability for administrators to define IP allow and deny lists, colloquially known as IP whitelist and blacklists.

## Installation
```
composer require madmatt/silverstripe-iplists
vendor/bin/sake dev/build flush=1
```

Visit `/admin/iplists` to define the allow and deny lists that you want.

## Configuration

By default, this module does not do anything beyond adding a new middleware into every request. This middleware does nothing on CLI. To have this module be useful, you need to configure one or more IP lists. Each IP list can contain multiple URI location rules, as well as both allow and deny rules to determine who can access the URI location rules you specify.

You can configure lists in two different ways: YML and CMS.

### YML configuration

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

### CMS configuration

This feature is still in a TODO stage. It is expected that CMS configuration should be able to view but not edit any configuration defined in YML. CMS configuration can be used to extend YML configuration (e.g. add additional IPs to the allow/deny lists), but not change what is defined in YML. Also, administrators will be able to define new lists purely in the CMS.


## Why 'allow' and 'deny' instead of 'whitelist' and 'blacklist'?

1. Allow and Deny are more accurate terms than white and black.
2. Allow and Deny aren't quasi-racist terms

See also:
1. https://www.clockwork.com/news/creating-inclusive-naming-conventions-in-technology/
2. https://www.theregister.co.uk/2019/09/03/chromium_microsoft_offensive/
3. https://twitter.com/andrestaltz/status/1030200563802230786
4. https://github.com/rails/rails/issues/33677
