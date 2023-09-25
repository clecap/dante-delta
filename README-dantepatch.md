
# README for Dante Patches on Mediawiki Core Files

This readme describes patches made to the Mediawiki system for the Dantewiki system.

## Philosophy

Patches are bad, since they reduce the compatibility with the ongoing development of the patched system. 
Nevertheless we here do some patches, becaus
* we are not sufficiently familiar with the concepts and social system of the Phabricator, where Mediawiki patches
  traditionally are entered 
* the process on Phabricator sometimes is very slow and burdensome
* we occasionally need patches which are not suitable for Mediawiki applications but necessary for Dantewiki.

This document describes these patches and our needs, so that they can be carried over to future Mediawiki versions.

## Patches

###  SVGReader.php patch

We added to the MW 1.39 version in line 78:
```	libxml_disable_entity_loader( false );  ```

The SVG files produced by drawio and dumped to Mediawiki export xml format cannot be
read in again via importDump.php in DanteBackup/DanteRestore.

These files seem to use external entities.
 
External entities are disallowed by Mediawiki for security reasons (eg: Upload of SVG files which 
can read out local files such as /etc/passwd and output the contents to the uploader).

Our security model is different. 
* We typically disallow file uploads to anonymous or even registered users.
* Dantewiki runs in a Docker container.
* Dantewiki is more of a throw-away container.

The patch enables us to upload drawio files from a dump.

Compare also: 
* https://phabricator.wikimedia.org/T86036 for the reason of this patch.
* https://www.php.net/manual/en/function.libxml-disable-entity-loader.php
