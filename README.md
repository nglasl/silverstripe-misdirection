# [misdirection](https://packagist.org/packages/nglasl/silverstripe-misdirection)

_The current release is **2.0.0**_

	This module will allow you to set up simple/regular expression link redirection
	mappings and customisation, either replacing the default automated URL handling or hooking into a page not found. This is useful for something such as legacy page redirection.

_**NOTE:** This repository has been pulled together using re-factored code from an existing module._

:bust_in_silhouette:

https://github.com/silverstripe-australia/silverstripe-linkmapping

## Requirement

* SilverStripe 3.1.X

## Getting Started

* Place this directory in the root of your SilverStripe installation.
* `/dev/build` to rebuild the database.

## Overview

This module is designed to function with or without the CMS module present.

All URLs to redirect and that are redirected to end up being unified so there is consistency with varied user input.

### Misdirection Admin

**screenshot of misdirection admin**

#### Link Mapping

**screenshots with the available field definitions**

simple / regular expression types

When multiple link mappings end up being matched, the one to be used is determined based on a priority field and how specific the definition is. Link mappings with identical priority will fall back to the oldest mapping by default.

To URL or page if CMS is present. Can validate external URL if you so wish.

Can customise response code.

Can chain link mappings, hence why the testing interface is so useful.

Hostname restriction under optional section.

```yaml
LinkMapping:
  priority: 'DESC'
```

#### Testing

![test](images/misdirection-testing.png)

**replace with a screenshot when actually testing**

This will retrieve the link mapping call stack for a given URL, and whether that reached the maximum request limit.

When a certain depth of link mappings has been reached, the server will return with a 404 response to prevent inefficient mappings or infinite recursion. The following is the default configuration:

```yaml
MisdirectionRequestFilter:
  maximum_requests: 9
```

**screenshot showing example of maximum number reached**

#### Bypass

You may bypass any link mappings or fallbacks by appending `?direct=1` to the URL, which may prove useful when locked out by erroneous link mappings.

**screenshot to demonstrate the bypass against the infinite loop above**

#### Vanity URL

You may create a vanity link mapping from your site tree element, however other matching link mappings with higher priority will take precedence.

**screenshot of CMS, maybe combined into one with the fallback below**

#### Fallbacks

These will be triggered when no link mappings are found, and the response will be a 404, but only if the CMS module is present.

You may either set a global fallback default under the site settings, or create a fallback from an individual site tree element.

**screenshot of page config level**
**screenshot of site config level**

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
