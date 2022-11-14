# drupal-module-helfi-gredi
Drupal module for Gredi image service.

Specification based on meeting with City of Helsinki 2022.06.21:

IF YOU DONT HAVE FORM DISPLAY `user.user.default` YOU HAVE TO PUT `field_gredi_dam_username` and `field_gredi_dam_password` MANUALLY IN YOUR USER EDIT FORM DISPLAY.

Target of the project
---------------------

We will create a module for Drupal 9.
Idea of the module is to fetch an image from 
the Gredi Media Bank.

Technical structure
-------------------

We should create a media type, to which image content is
fetched creating a Stream Wrapper (remote entity).

The original image is only stored in Gredi service.
New media module will contain derivative images of different size.

Metadata will be stored to the media type.
Metadata of the picture will be queried separately.

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
Each user has license of their own. 9 groups are based on CoH departments.

Repository
----------

Public repository for Gredi module is be created in GitHub,
https://github.com/City-of-Helsinki/drupal-module-helfi-gredi

Module structure could be like https://github.com/City-of-Helsinki/drupal-module-helfi-tpr.

Testing
-------

PHP code is tested by creating PHPUnit tests.

SNYK testing can be done with npm.
- You got to have npm(nodejs) installed to your local computer.
- npm install
- npm_modules created by install is included in the .gitignore -file
- Snyk account can be created by calling:
```
   snyk auth
```
- Snyk token should contain your token created by authentication:
```
export SNYK_TOKEN=<YOUR_SNYK_TOKEN>
```

- To run vulnerability tests:
```
   npm run snyk
```

PHPStan can be used to analyse php code.
- Rule level is set to 6 in the phpstan.neon
- To run tests:
```
vendor/bin/phpstan analyse src tests
```

Code Sniffer can be used to analyze the Drupal code, too.
- Sniffer is using squizlabs/php_codesniffer and drupal/coder packages.
- To run tests:
```
vendor/bin/phpcs --standard=Drupal helfi_gredi_image.module
```

Future development:
-------------------

How the content creator is selecting images from Gredi service is still open.
JavaScript widget will probably be created for this purpose.

Search will later be implemented by using tags.
