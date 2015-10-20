# [misdirection](https://packagist.org/packages/nglasl/silverstripe-misdirection)

_The current release is **2.0.1**_

	A module for SilverStripe which will allow both simple and regular expression link
	redirections based on customisable mappings, either hooking into a page not found or
	replacing the default automated URL handling.

This is based upon the existing [link mapping](https://github.com/silverstripe-australia/link-mapping) module, aiming to provide a more robust solution for both users and developers alike, with further support and customisation!

## Requirement

* SilverStripe 3.1.X

This module does **not** require the CMS.

## Getting Started

* Place the module under your root project directory.
* `/dev/build`
* Select `Misdirection` through the CMS.
* Create link mappings.

## Overview

### Link Mappings

These allow both simple and regular expression link redirections, based on priority and specificity. They can be used for legacy page redirection, vanity URLs, or redirection based on specific URL patterns.

![link-mapping](images/misdirection-link-mapping.png)

The link mapping with the highest priority and greatest specificity will be used, and replaces the default automated URL handling out of the box. This default behaviour may be configured to only hook into a page not found:

```yaml
MisdirectionRequestFilter:
  enforce_misdirection: false
```

When there are multiple matches, the link mapping first created will be used. This default behaviour may be configured to prioritise the link mapping most recently created:

```yaml
LinkMapping:
  priority: 'ASC'
```

### Content Authors

![vanity-URL-and-fallback](images/misdirection-vanity-URL-and-fallback.png)

#### Vanity URL

A content author may directly create a link mapping from a page, however it should be noted that these are instantiated with a low priority of `2`, and therefore other link mappings with higher priority will take precedence.

#### Fallback

This will be triggered when a page not found is encountered. It is possible to configure a global fallback through the site configuration by an administrator.

* Select `Settings`
* Select `Pages`

#### Testing

![testing](images/misdirection-testing.png)

This will retrieve the link mapping call stack for a given URL, and whether that reached the maximum request limit. The result is traversed on server side to detect any inefficient mappings or infinite loops.

When a certain depth of link mappings has been reached, the server will return with a 404 response to prevent inefficient mappings or infinite recursion. The following is the default configuration:

```yaml
MisdirectionRequestFilter:
  maximum_requests: 9
```

This stack will be traversed server side, rather than redirecting the user back and forth until the maximum.

![testing-maximum-requests](images/misdirection-testing-maximum-requests.png)

#### Bypass

You may bypass any link mappings or fallbacks by appending `?direct=1` to the URL, which may prove useful when locked out by erroneous link mappings. This does not work using the test interface.

**screenshot to demonstrate the bypass against the infinite loop above**

### Automated URL Handling

The custom request filter will prevent issues around existing director rules such as /admin or /dev.

To disable the automated URL handling completely..

```yaml
MisdirectionRequestFilter:
  replace_default: true
```

#### Misdirection Historical Link Mapping Task

The following may be used to instantiate a link mapping for each site tree version URL, when replacing the default automated URL handling (currently only supported by MySQL).

`/dev/tasks/MisdirectionHistoricalLinkMappingTask`

#### Site Tree Automatic Link Mapping

When the URL segment of a site tree element has been updated, a link mapping will automatically be created. This functionality will be removed as soon as you enable SilverStripe's default automated URL handling (as it will no longer be required).

![replace-default](images/misdirection-replace-default.gif)

## Maintainer Contact

	Nathan Glasl, nathan@silverstripe.com.au
