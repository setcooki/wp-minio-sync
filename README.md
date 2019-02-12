# WP Minio Sync (Media Cloud Extension)

_A Wordpress (ilab media tools) extension to synchronise cloud based Wordpress instances media via Minio webhooks_

**What does this plugin do?**

You are using Minio S3 cloud storage for Wordpress (together with https://wordpress.org/plugins/ilab-media-tools/) ... tbd


## 1. Usage

Download plugin .zip from `/dist` folder and install with wordpress

## 2. Minio setup

...tbd

## 3. Development

1. clone repo
2. do a `npm install` for dependencies
3. run `grunt dist` to compile distributable plugin dist
4. do a `composer install` (provided you have composer installed globally)

you should tag new versions of plugin by running:

```bash
$ sh ./tag.sh $version $message
```

where `$version` is the version string (use `git describe --tags --long` for current version) and
`$message` is the optional tag message string. The version is automatically updated in plugin bootstrap
php file.