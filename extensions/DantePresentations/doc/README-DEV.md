

# Dev Notes #


## Preserving the Preview Style

How do we ensure that the preview looks as much as possible like the normal view?
* Twist mediawikiEndpoint.css
* In mediawikiEndpoint.php add class names to the wrapping element.


## Toc Style
* In DantePresentations.php add a hard coded style line into the header.



## Clicking links in the presentations with PresentationRequest ##

Currently it looks like we cannot click on a link directly in the presentation.

If we do, the presentation aborts.



## Starting multiple presentations with PresentationRequest ##

If we have more than one external monitor then we cannot start multiple presentations.
It leads to a request by chromecast to abort one of the presentations.


# Multi Monitor API #

It is possible that the multi monitor API allows a better approach here, with mutliple presentations on
a multiple of external screen.



 










