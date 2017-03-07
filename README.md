# VctlsEntityBundle

Generic entity management bundle.  
The goal of this bundle is to enable basic CRUD actions on database entities using a single controller and series of views for any entity.  
**Disclaimer :** this bundle is far from complete, untested, and not supposed to be used in a production environment, ever!

## Prerequisites
  - Bootstrap 3
  - DataTables


## Setup

Add the repo to the `composer.json` :  
```json
{
    "repositories": [
        {
            "type": "git",
            "url": "https://github.com/vctls/entity-bundle.git"
        }
     ]
 }
```

Add the bundle to `composer.json` :
```json
{
    "require-dev": {
        "vctls/entity-bundle": "dev-master"
    }
}
```

Run `composer update`

### Enable the bundle
```php
<?php
// app/AppKernel.php
// ...

class AppKernel extends Kernel
{
    // ...

    public function registerBundles()
    {
        // ...
        if (in_array($this->getEnvironment(), array('dev', 'test'), true)) {
            // ...
            $bundles[] = new Vctls\EntityBundle\VctlsEntityBundle();
        );

        // ...
    }
}
```

**Also, enable KnpMenuBundle in dev or prod, in order for the menu builder service to work!**

### Configuration
Complete `routing_dev.yml` :
```yaml
vctls_entity:
    resource: "@VctlsEntityBundle/Resources/config/routing.yml"
    prefix:   /
```

### Setup frontend dependencies

### Usage
Enter the index route manually, or use the integrated Menu builder to create a menu to access all your entities.
By default, the index route is `/entity/index/{entityName}`. Use the entity alias, like `AppBundle:MyEntity`.