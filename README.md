# Symfony Functional Testing Suite


## Issues:
In case of the error:
` The service "nelmio_alice.property_accessor.std" has a dependency on a non-existent service "property_accessor". Did you mean this: "nelmio_alice.property_accessor.std"?`
the solution has to be adding the `property_access: ~` to the framework configuration:
(`config/packages/framework.yaml`).

## Prerequisites

1. Docker

## Check 