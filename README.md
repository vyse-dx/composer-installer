# Vyse Composer Installer

Installs the top level vyse bin symlinks from any included Vyse packages.

Run this to tell Composer to allow that to happen -

```
docker run --rm -v $(pwd):/workspace ghcr.io/vyse-dx/devcontainer:latest composer --no-plugins config allow-plugins.vyse/installer true
```
