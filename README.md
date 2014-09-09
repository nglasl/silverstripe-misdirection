# [misdirection](https://github.com/nglasl)

_**NOTE:** This branch is for development only._

	This module will allow you to set up simple/regular expression link redirection mappings and customisation, either replacing the default automated URL handling or hooking into a page not found. This is useful for something such as legacy page redirection.

_**NOTE:** This repository has been pulled together using re-factored code from an existing module._

:bust_in_silhouette:

https://github.com/ajshort/silverstripe-linkmapping

## Requirements

* SilverStripe 3.1.X

## Getting Started

* Place this directory in the root of your SilverStripe installation.
* `/dev/build` to rebuild the database.

## Overview

This module is designed to function with or without the CMS module present.

### Automated URL Handling

SilverStripe's default automated URL handling will be disabled when using this module out of the box, however it may be enabled again through configuration.

```yml
LinkMappingRequestFilter:
  replace_default: false
```

When a certain depth of link mappings has been reached, the server will return with a 404 response to prevent inefficient mappings or infinite recursion. The following is the default configuration:

```yml
LinkMappingRequestFilter:
  maximum_requests: 9
```

### Link Mappings

#### Priority

When multiple link mappings end up being matched, the one to be used is determined based on a priority field and how specific the definition is. Link mappings with identical priority will fall back to the oldest mapping by default.

```yml
LinkMapping:
  priority: 'DESC'
```

#### Testing

![test](images/link-mapping-test.png)

This will retrieve the link mapping call stack for a given URL, and whether that reached the maximum request limit.

### Site Tree

#### Automatic Link Mapping

When the URL segment of a site tree element has been updated, a link mapping will automatically be created. This functionality will be removed as soon as you enable SilverStripe's default automated URL handling (as it will no longer be required).

#### Vanity URL

You may create a vanity link mapping from your site tree element, however other matching link mappings with higher priority will take precedence.

#### Fallbacks

These will be triggered when no link mappings are found, and the response will be a 404.

You may either set a global fallback default under the site settings, or create a fallback from an individual site tree element.

## Maintainer Contacts

	Nathan Glasl, nathan@silverstripe.com.au
