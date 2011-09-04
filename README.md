MaksaDemoBundle
======================

## Installation

To install this bundle, you'll need both the [MaksaUlinkBundle](/cravler/MaksaUlinkBundle)
and this bundle. Installation depends on how your project is setup:

### Step 1: Installation using the `bin/vendors` method

If you're using the `bin/vendors` method to manage your vendor libraries,
add the following entries to the `deps` in the root of your project file:

```
[MaksaDemoBundle]
    git=http://github.com/cravler/MaksaDemoBundle.git
    target=../src/Maksa/DemoBundle
```

**NOTE**: This location and syntax of the `deps` file changed after BETA4. If you're
using an older version of the deps system, you may need to swap the order of the items
in the `deps` file.

Next, update your vendors by running:

``` bash
$ php bin/vendors install
```

Great! Now skip down to *Step 2*.

### Step 1 (alternative): Installation with submodules

``` bash
$ mkdir -pv src/Maksa/DemoBundle
$ git submodule add git://github.com/cravler/MaksaDemoBundle.git src/Maksa/DemoBundle
```

### Step2: Enable the bundle

Finally, enable the bundle in the kernel:

``` php
<?php
// app/AppKernel.php

public function registerBundles()
{
    $bundles = array(
        // ...

        new Maksa\DemoBundle\MaksaDemoBundle(),
    );
}
```

## Routing example

``` yaml
_maksaDemoBundle:
    resource: "@MaksaDemoBundle/Controller/DemoController.php"
    type:     annotation
    prefix:   /maksa_demo
```
