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

The following customisation is available out of the box, allowing both simple and regular expression link redirections based on priority. This is very useful for legacy mapping or for having a page vanity URL.

![link-mapping](images/misdirection-link-mapping.png)

When two link mappings have the same priority, the first created will take precedence. This behaviour can be defined.

```yaml
LinkMapping:
  priority: 'DESC'
```

These may also be chained.

#### Site Tree

![site-tree](images/misdirection-site-tree.png)

##### Vanity URL

You may create a vanity link mapping from your site tree element, however other matching link mappings with higher priority will take precedence.

##### Fallbacks

These will be triggered when no link mappings are found, and the response will be a 404, but only if the CMS module is present.

You may either set a global fallback default under the site settings, or create a fallback from an individual site tree element.

![site-config](images/misdirection-site-config.png)

#### Testing

![testing](images/misdirection-testing.png)

This will retrieve the link mapping call stack for a given URL, and whether that reached the maximum request limit.

When a certain depth of link mappings has been reached, the server will return with a 404 response to prevent inefficient mappings or infinite recursion. The following is the default configuration:

```yaml
MisdirectionRequestFilter:
  maximum_requests: 9
```

This stack will be traversed server side, rather than redirecting the user back and forth until the maximum.

![testing-maximum](images/misdirection-testing-maximum.png)

#### Bypass

You may bypass any link mappings or fallbacks by appending `?direct=1` to the URL, which may prove useful when locked out by erroneous link mappings. This does not work using the test interface.

**screenshot to demonstrate the bypass against the infinite loop above**

### Automated URL Handling

The custom request filter will prevent issues around existing director rules such as /admin or /dev.

To disable link mappings from taking precedence over the default automated URL handling..

```yaml
MisdirectionRequestFilter:
  enforce_misdirection: false
```

To disable the automated URL handling completely..

```yaml
MisdirectionRequestFilter:
  replace_default: true
```

#### Misdirection Historical Link Mapping Task

The following may be used to instantiate a link mapping for each site tree version URL, when replacing the default automated URL handling (currently only supported by MySQL).

`/dev/tasks/MisdirectionHistoricalLinkMappingTask`

**images of using the task, and how things are populated**

#### Site Tree Automatic Link Mapping

When the URL segment of a site tree element has been updated, a link mapping will automatically be created. This functionality will be removed as soon as you enable SilverStripe's default automated URL handling (as it will no longer be required).

## Maintainer Contact

	Nathan Glasl, nathan@silverstripe.com.au
