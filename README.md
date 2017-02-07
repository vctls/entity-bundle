# VctlsEntityBundle

Generic entity management bundle.
The goal of this bundle is to enable basic CRUD actions on database entities using a single controller and series of views for any entity. 

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
    "require": {
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
        $bundles = array(
            // ...
            new Vctls\EntityBundle\VctlsEntityBundle(),
        );

        // ...
    }
}
```

### Configuration
Complete `routing.yml` :
```yaml
vctls_entity:
    resource: "@VctlsEntityBundle/Resources/config/routing.yml"
    prefix:   /
```

### Setup frontend dependencies
