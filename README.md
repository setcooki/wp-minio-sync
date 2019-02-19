# WP Minio Sync (Media Cloud Extension)

_A Wordpress (ilab media tools) extension to synchronise cloud based Wordpress instances media via Minio webhooks_

**What does this plugin do?**

You are using or planning to use Minio S3 cloud storage (https://www.minio.io/) together with ILAB´s Wordpress media cloud plugin https://wordpress.org/plugins/ilab-media-tools/)
and want to share your media across multiple Wordpress projects with independent databases? You need this plugin then - because even though you can connected any Wordpress
instance to your Minio cloud - Wordpress will not know about the media since it needs post and post meta data together with your media file in order to work with it. 

Since the Media cloud plugin is not able to notify other wordpress installs of any changes to your media library we can use Minio´s webhook capabilities to notify any
Minio connected Wordpress instance of changes to your media library. Once a put or delete webhook is fired the needed post and post meta will be created or updated accordingly.

Finally you can have a real cloud media library across theoretically dozens of independent wordpress installs.


## 1. Usage

1) Download plugin .zip from `/dist` folder and install with Wordpress

2) Once installed go to the plugin overview page (`/wp-admin/plugins.php`) and under the plugin details you find a link to the Minio webhook target including 
the access token. This url must be defined as endpoint in your Minio´s webhook server configuration.


## 2. Minio

In order to create your Minio webhooks please refer to https://docs.minio.io/docs/minio-bucket-notification-guide.html

You can enable webhooks by changing your Minio server´s config by:

```
$ mc admin config get myminio/ > /tmp/myconfig
```

Enable the webhook and add your endpoints then:

```
$ mc admin config set myminio < /tmp/myconfig
```

After you have added your webhook add the events to your buckets:

```
$ mc event add <host>/<bucket> arn:minio:sqs::1:webhook --event put --debug
$ mc event add <host>/<bucket> arn:minio:sqs::1:webhook --event delete --debug
```

Restart the server:

```
$ mc admin service restart <host>
```


## 3. Development (only)

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
