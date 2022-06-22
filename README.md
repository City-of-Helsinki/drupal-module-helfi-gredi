# drupal-module-helfi-gredi
Drupal module for Gredi image service.

Specification based on meeting with City of Helsinki 2022.06.21:

Target of the project
---------------------

We will create a module for Drupal 9.
Idea of the module is to fetch an image from 
the Gredi Media Bank.

Technical structure
-------------------

We should create a media type, to which image content is
fetched creating a Stream Wrapper (remote entity).

Original image stay in Gredi service.
New media module will contain derivative images of different size.

Metadata will be stored to the media type.
Metadata of the picture, will be queried separately. (This is little bit open).

Entity will be part of Drupal backend.

Module contains only images, not embeds.
Unused derivative image will be deleted from Drupal Media.

Environment
-----------

Module should support either Media Library or Media Entity Browser modules
or both. Module should be compatible with CoH Drupal Platform.

Module will be used in all 9 instances.
One module configuration will query images belong to one user group of the CoH.
Each instance of the module will have username and password (aka. secrets) of their own.
Each user has license of their own. 9 groups are base on CoH departments.

Repository
----------

Public repository for Gredi module is be created in GitHub,
https://github.com/City-of-Helsinki/drupal-module-helfi-gredi

Module structure could be like https://github.com/City-of-Helsinki/drupal-module-helfi-tpr.

Testing
-------

PHP code is tested by creating PHPUnit tests.

Future development:
-------------------

How the content creator is selecting images from Gredi service is still open.
JavaScript widget will probably be created for this purpose.

Search will later be implemented by using tags.
