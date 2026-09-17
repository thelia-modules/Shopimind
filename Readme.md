# ShopiMind

Thelia module for integration with the **ShopiMind** marketing automation platform (ShopiMind V5). This module synchronizes your Thelia store data with ShopiMind to automate your marketing campaigns (emails, SMS, push notifications, etc.).

## Version

- **Module version**: 5.0.0
- **Requirements**: Thelia 2.5 (2.5.0 or later), PHP 8.0.2 or later, PHP extensions `curl` and `json`
- **Author**: ShopiMind
- **Contact**: contact@shopimind.com

### Version numbers

| Value | Defined in | Rule |
|-------|------------|------|
| Module version, sent as `module_version` | `Config/module.xml` (`<version>`) and `constants.php` (`module.version`) | Incremented on every release (5.0.1, 5.0.2, ...), in both files. Thelia runs the update of the module only when the version of `Config/module.xml` differs from the installed one. |
| Protocol version, sent in the `client-version` header | `constants.php` (`module.client_version`) | Stays at `5.0.0`: ShopiMind V5 requires `>= 5.0.0` for synchronizations and for the "module connection" step of the migration wizard. |

## Installation

The module lives in `<thelia_root>/local/modules/Shopimind`. The folder name is case-sensitive: it must be `Shopimind`.

The `thelia/shopimind-module` package published on Packagist does not provide ShopiMind 5.0.x: do not install or update this module with `composer require` or `composer update`.

### Migrating from the ShopiMind V4 module (1.0.x)

Do not upload the 5.0.x zip over the V4 module: both modules have the same name in Thelia, and the upload fails as described above.

1. Delete the old module from the back office (*Modules* > *ShopiMind* > *Delete*). Its uninstallation drops its tables, whether *Delete also module data* is checked or not. Check that the `<thelia_root>/local/modules/Shopimind` folder no longer exists before going on. If the V4 module was installed with Composer, also run `composer remove thelia/shopimind-module`, so that a later `composer install` or `composer update` does not bring the V4 files back.
2. Copy the new module into `<thelia_root>/local/modules/Shopimind`.
3. Activate the module in the Thelia administration panel, then clear the cache (`php Thelia cache:clear`).
4. Enter the ShopiMind V5 API credentials in the module configuration page and save. ShopiMind records the module version only when the module connects: until the configuration is saved, the "module connection" step of the ShopiMind migration wizard stays pending and synchronizations are refused.

If the old module was replaced without being uninstalled: delete the old folder before copying the new one, so that no V4 file remains, then run `php Thelia cache:clear && php Thelia module:refresh`. The upgrade from 1.0.x to 5.0.x adds the missing `script_url` column, drops the V4 tables and keeps the saved credentials. Then open the module configuration page and click *Save* to connect the module to ShopiMind V5: until then, the page shows *ShopiMind has not received the installed version of the module yet*. If ShopiMind refuses the saved credentials (API key regenerated in the migration wizard), enter the V5 API ID and password, then save.

### New installation

1. Copy the `Shopimind` folder into `<thelia_root>/local/modules/`, so that `<thelia_root>/local/modules/Shopimind/Config/module.xml` exists. When no ShopiMind module is listed in *Modules*, you can upload the zip in *Modules* > *Install or update a module* instead.
2. Activate the module in the Thelia administration panel (*Modules*), or run `php Thelia module:refresh` then `php Thelia module:activate Shopimind`.
3. Enter your ShopiMind API credentials in the module configuration page and save.

### Updating from 5.0.x

An update keeps the API credentials, the connection to ShopiMind and the settings. Use the command line when you can, and never the zip upload over the installed module (see the warning above).

1. Replace the module files. Put the new `Shopimind` folder outside `local/modules` first, then delete `<thelia_root>/local/modules/Shopimind` and move the new folder in its place, so that the shop runs without the module files only for a moment. Keep no copy of the old folder inside `local/modules` (for example `Shopimind.old`): Thelia reads the `Config/module.xml` of every folder there, and an old copy makes it record the wrong version. If the ShopiMind log is written in the module folder (`Shopimind/logs/shopimind.log`, used when the Thelia log folder is not writable), copy it first: replacing the folder deletes it.
2. Right away, clear the cache and run the update: `php Thelia cache:clear && php Thelia module:refresh`. Thelia compares the version of `Config/module.xml` with the installed one and runs the update of the module, at the latest during `module:refresh`. The module stays activated. If `php Thelia cache:clear` fails, delete the `<thelia_root>/var/cache/<environment>` folder (for example `var/cache/prod`), then run `php Thelia module:refresh`.
3. Open the module configuration page. A module whose connection was saved by a previous update declares its new version to ShopiMind by itself (see *Reconnection after an update or an activation*). The first update from a version that did not save its connection yet (5.0.0) needs one click on *Save*: until then, the page shows *ShopiMind has not received the installed version of the module yet*.
4. In *Modules*, check that ShopiMind shows the new version and is still activated.

Run the commands in the environment of the shop. `php Thelia` reads `APP_ENV` from the `.env` file and uses `dev` when it is not set there. If the web server sets `APP_ENV` itself (for example in the virtual host), pass the same value: `php Thelia cache:clear --env=prod` and `php Thelia module:refresh --env=prod`. Without command line access, replace the files, click *Flush the Thelia internal cache* in *Configuration* > *Advanced configuration*, then open *Modules*: Thelia runs the update when it rebuilds its cache, and checks the version again each time *Modules* is opened.

Without access to the module files, update from the back office: delete ShopiMind in *Modules* with *Delete also module data* unchecked, upload the new zip in *Modules* > *Install or update a module*, then activate ShopiMind. The credentials and settings are kept (see *Uninstalling*), but hook positions, hooks removed from ShopiMind and the ShopiMind permissions of back-office profiles return to their default: check them after the update.

If the update fails, the error is displayed in the terminal, or on the *Modules* page when the update runs from that page. When it runs while Thelia rebuilds its cache during a web request, that request ends with an error page (HTTP 500) and the next requests work. The error is also written to the PHP error log and, when the log level is not *Off*, to the ShopiMind log. Thelia may have recorded the new version number anyway, because MySQL applies table changes immediately: the update is then not run again by itself. Once the cause is fixed, deactivate then activate ShopiMind in *Modules*: the activation completes the missing tables, columns and settings, and keeps the credentials and the connection.

### Re-installing the module

To restore the files of the version already installed:

1. Replace the folder as in step 1 of the update, then run `php Thelia cache:clear`. Thelia rebuilds its cache and attaches the module hooks again.
2. If a ShopiMind table or setting was deleted by hand, deactivate then activate ShopiMind in *Modules*: the activation recreates what is missing and keeps the credentials and settings in place.

`php Thelia module:refresh` does nothing when the version number has not changed, and uploading the zip of the installed version is refused by Thelia. Deleting the module and installing it again also works: see *Uninstalling* for what is kept.

### Uninstalling

*Modules* > *ShopiMind* > *Delete* removes the `<thelia_root>/local/modules/Shopimind` folder, including the logs written in its `logs/` folder. The *Delete also module data* checkbox of the confirmation window decides what else is deleted:

| *Delete also module data* | Result |
|---------------------------|--------|
| Unchecked (default) | Kept: the `shopimind` table (API credentials, connection status, options, tracking script URL set by ShopiMind) and the `shopimind_*` settings of the Thelia `config` table. The `shopimind.log` file of the Thelia log folder is kept; a log written in the module folder is deleted with it. Once the module is copied again and activated, it is connected with the same credentials. |
| Checked | Deleted: the `shopimind` table, the `shopimind_*` settings of the Thelia `config` table, and the `shopimind.log` and `shopimind.log.1` files. A new installation starts disconnected, with the default settings: enter the API credentials again and save. |

In both cases, the tables left by the V4 module (`shopimind_sync_status`, `shopimind_sync_errors`) are dropped if they still exist. ShopiMind is not notified: its calls to the shop fail until the module is installed and activated again.

## Configuration

Access the module configuration via **Administration > Modules > ShopiMind**.

### Available options

| Option | Description |
|--------|-------------|
| **API ID** | API identifier provided by ShopiMind |
| **API Password** | API password provided by ShopiMind |
| **Real-time synchronization** | Sends data changes to ShopiMind at the end of each request. Enabled by default |
| **Nominative reductions** | Generates discount codes restricted to the target customer |
| **Cumulative vouchers** | Allows combining discount codes |
| **Out of stock product disabling** | Sends out of stock products as inactive |
| **Script tag** | Injects the ShopiMind tracking script. Enabled by default |
| **Hide the tag on checkout pages** | Does not inject the tag on the delivery and invoice steps, nor on the payment page. Disabled by default |
| **Cache workaround** | Loads the customer and cart data of the tag through `/shopimind/spmq` after the page, for shops behind a page cache (Varnish, CDN). Disabled by default: Thelia does not cache pages |
| **Log level** | *Off*, *Errors only*, *Info (recommended)* or *Debug (verbose)*, in the *Developer / Debug* section. Off by default; logging enabled with the *Log* checkbox of a previous version is read as *Info* |
| **Confirmed statuses** | Order statuses considered as confirmed |
| **Customer titles considered as Mr / Mme** | Mapping of Thelia customer titles to the ShopiMind gender. Until it is saved, Thelia's default titles are used: 1 (Mr) as Mr, 2 and 3 (Mrs, Miss) as Mme. |

Settings that were never saved get their default value when the module is activated or upgraded; values already saved are kept.

### Developer / Debug

| Option | Description |
|--------|-------------|
| **ShopiMind API URL** | Replaces `https://core.shopimind.com` for the calls of the module to ShopiMind, including the connection made when the configuration is saved |
| **Tracking script URL** | Replaces the domain of the tracking script (`https://v2.app-spm.com`, or the URL set by ShopiMind through `/shopimind/config`) |

Both URLs are used only when the log level is *Debug*: lowering the level restores the default URLs without clearing the fields. Leave a field empty to use the default URL. Only full `https://` or `http://` URLs are accepted, without login, query string or anchor. The URL in use is displayed under each field.

### Reconnection after an update or an activation

ShopiMind records the module version when the module connects, that is when the configuration is saved. A connected module then declares a new version by itself, with the shop URL and time zone of its last connection accepted by ShopiMind:
- when Thelia updates it (*Modules* page, `php Thelia module:refresh`, or first request after the cache is rebuilt), if it is activated;
- when it is activated, for example after a reinstallation that kept the module data.

Nothing is sent when the module is not connected or when its version was already declared. Nothing is sent either when no connection was saved yet (update from the V4 module, or from a 5.0.x version that did not save its connection): the configuration page then shows *ShopiMind has not received the installed version of the module yet*, and saving the configuration once connects the module and saves its connection.

A reconnection never blocks the activation or the update: ShopiMind has 5 seconds to answer, 3 of them to establish the connection. On failure, the credentials and the connection status are kept, the error is written to the PHP error log and, when the log level is not *Off*, to the ShopiMind log, and the configuration page shows the same message. A timeout can be reported even though ShopiMind recorded the connection: saving the configuration once clears the message.

A copy of the shop (staging, preproduction) made from the production database declares its module version to ShopiMind in place of the production shop when the module is updated or activated there. Before updating or activating the module on such a copy, disconnect it: `UPDATE shopimind SET is_connected = 0;`.

## Features

### Data synchronization

The module synchronizes **13 entity types** with ShopiMind:

| Entity | Description |
|--------|-------------|
| `customers` | Customers |
| `customers_addresses` | Customer addresses |
| `customers_groups` | Customer groups (requires CustomerFamily) |
| `newsletter_subscribers` | Newsletter subscribers |
| `orders` | Orders |
| `orders_statuses` | Order statuses |
| `orders_carriers` | Carriers (delivery modules) |
| `products` | Products |
| `products_variations` | Product variations (PSE) |
| `products_images` | Product images |
| `products_categories` | Categories |
| `products_manufacturers` | Brands |
| `vouchers` | Discount codes |

Data notes:
- **Customers**: Thelia core stores no birth date. `birth_date` is read from a customer meta data (`meta_data` table, keys `birth_date`, `birthdate`, `birthday` or `date_of_birth`, format `YYYY-MM-DD`) when a module provides one, and is `null` otherwise. `is_active` follows Thelia's login rule: a customer is inactive only when email confirmation is required and the account is not confirmed.
- **Orders**: `carrier_id` is the delivery module of the order and `shipping_number` the delivery reference entered in the back office. `voucher_used` lists all the discount codes of the order separated by commas (Thelia allows cumulative coupons), codes starting with `SPM-` or `SPM_` first; `voucher_value` is the total discount of the order, taxes included (free shipping is not counted). Both are `null` when the order has no discount code.
- **Products**: `price_discount` equals `price` when the product has no active promotion. For a variation, `price_discount` is sent only when it is lower than `price`.
- **Images**: when no image URL can be built (file missing on disk, image processing failure), `https://placehold.co/300x300` is sent, as for the `image_link` of products and variations.
- **Variations**: `image_link` is the first image linked to the variation, otherwise the first visible image of the product.
- **Brands**: `name` is the brand title in the default language of the shop, otherwise the first title filled in, otherwise `#` followed by the brand ID.
- **Vouchers**: a code generated by ShopiMind can be used once in total; a nominative code can only be used by its customer. `is_used` is true when the code has no use left.

### Synchronization modes

#### Real-time synchronization

When enabled, Thelia model events (creation, update, deletion) are collected during the request and sent to ShopiMind once, at the end of the request (`lib/SyncBuffer.php`). Order creations and updates are always sent, even when real-time synchronization is disabled. Carriers are sent when a delivery module is installed, activated, deactivated or edited.

#### Passive synchronization (batch)

ShopiMind triggers batch synchronizations by calling `POST /shopimind/synchronize` (HMAC-signed, `application/x-www-form-urlencoded`). Supported parameters:

| Parameter | Description |
|-----------|-------------|
| `type` | Entity type to synchronize |
| `start` | Pagination offset (default: 0) |
| `limit` | Number of items per page (default: 20) |
| `ids` | Specific IDs to synchronize, comma-separated |
| `lastUpdate` | ISO 8601 date: only items updated since this date (`last_update` is also accepted) |
| `just_count` | Returns only the total count |
| `id_shop_ask_syncs` | Synchronization step identifier, forwarded to the ShopiMind API in the `X-Shopimind-Sync-Id` header |

### Webhooks

The module exposes webhooks called by ShopiMind (HMAC-signed, `application/x-www-form-urlencoded`):

| Webhook | Route | Description |
|---------|-------|-------------|
| `SpmCustomers` | `POST /shopimind/customers` | Customer creation (response: `success`, `message` and, when the customer is created, `id_customer`, the ID of the created customer; `id_customer` is `false` when the creation fails and absent from the other error responses) |
| `SpmSubscribeCustomer` | `POST /shopimind/subscribe-customer` | Newsletter subscription (`id_customer`: customer ID or email, optional `action`: `subscribe` or `unsubscribe`) |
| `SpmVouchers` | `POST /shopimind/vouchers` | Discount code creation |
| `SpmConfigController` | `POST /shopimind/config` | Tracking script URL (`set_script_url`, `get_script_url`, `reset_script_url`) |

## Thelia hooks

| Hook | Description |
|------|-------------|
| `main.head-bottom` | Tracking script injection at the end of `<head>`: `_spmq`, `v5.js` and the cart synchronization script (`Workers/Scripts/spm-cart.js`, embedded in the tag). Cart amounts are computed as on the storefront, with the tax country of Thelia's tax engine: delivery address chosen during checkout, otherwise the customer's default address, otherwise the default country |
| `main.body-bottom` | Fallback for a template without `main.head-bottom`. The tag is injected only once per page |
| `module.configuration` | Back-office configuration page |

## Module structure

```
Shopimind/
├── Config/                    # module.xml, routing, SQL schema, hooks
├── Controller/                # Configuration, script URL, cart data
├── Data/                      # Data formatters for each entity
├── EventListeners/            # Listeners for real-time synchronization
├── Form/                      # Back-office form
├── Hook/                      # Tracking script hook
├── I18n/                      # Translations
├── lib/                       # Utils (authentication, logs), ShopConnection (connection to ShopiMind), SyncBuffer, SyncHttpClient, SpmTag (tag data)
├── Model/                     # Propel models
├── PassiveSynchronization/    # Passive synchronization handlers
├── SpmWebHook/                # Incoming webhooks
├── Workers/                   # Front scripts (platform.js, web push service worker, spm-cart.js)
├── templates/                 # Admin templates
├── vendor-module/             # Composer dependencies (ShopiMind SDK)
├── constants.php              # URLs and version numbers
└── parameters.yml             # Event listener priorities
```

## API endpoints

The module exposes only the routes below.

| Method | Endpoint | Called by | Description |
|--------|----------|-----------|-------------|
| POST | `/shopimind/synchronize` | ShopiMind, signed | Passive synchronization |
| POST | `/shopimind/customers` | ShopiMind, signed | Customers webhook |
| POST | `/shopimind/subscribe-customer` | ShopiMind, signed | Newsletter subscription webhook |
| POST | `/shopimind/vouchers` | ShopiMind, signed | Vouchers webhook |
| POST | `/shopimind/config` | ShopiMind, signed | Script URL configuration |
| POST | `/shopimind/logs` | ShopiMind, signed | Log reading and clearing, when logs are enabled |
| GET | `/shopimind/platform` | Browser, URL given by ShopiMind | Web push platform script |
| GET | `/shopimind/web-push-service-worker` | Browser, URL given by ShopiMind | Web push service worker |
| GET | `/shopimind/platform.js`, `/shopimind/web-push-service-worker.js`, `/shopimind/spm-cart.js` | Browser | Former URLs, kept for pages and web push subscriptions created with a previous version |
| GET | `/shopimind/cart-data` | Storefront tag | Current session cart, read by the cart synchronization script |
| GET | `/shopimind/spmq` | Storefront tag | Customer and cart data of the tag when the cache workaround is enabled |
| POST | `/admin/module/shopimind/configuration` | Back office | Configuration form |

The tag embeds the cart synchronization script and ShopiMind uses the URLs without extension: with the nginx configuration recommended by Thelia, the `location` block of static files answers 404 to `.js` URLs without passing them to Thelia. To serve the former `.js` URLs under nginx, add `try_files $uri @rewriteapp;` to that block.

### Security

- ShopiMind calls are `POST` requests with an `application/x-www-form-urlencoded` body and the `Shopimind-Client-Identifiant` and `Shopimind-Token` headers. `Utils::authorizeSpmRequest()` checks each call before any processing: the module must be connected, the identifier must be the API ID, and the token must be the HMAC-SHA256 of the raw body (sorted keys), keyed with the SHA-256 hash of the secret part of the API password. Both comparisons are constant-time.
- A rejected call gets an HTTP 401 JSON response (`Unauthorized.` or `Module is not connected.`). The log records which check failed, never the token or the secret.
- Storefront routes only return data from the visitor's own session. Thelia never saves them as the page to return to after login.
- Saving the configuration requires the update permission on the module and the form's CSRF token.
- `/shopimind/logs` also requires a recent signed timestamp (`testConnection`, 15 minutes of tolerance with the server clock), so that an intercepted request cannot be replayed later, and answers only when logs are enabled (HTTP 403 `Logs are disabled.` otherwise). Its response never contains the file path, is limited to 1 MB and masks the API password.
- An error while processing a ShopiMind call returns a JSON response (HTTP 500, `Internal error: ...`) and is logged, never an HTML error page.

## Error handling

The module must never interrupt the shop:
- Real-time synchronization runs at the end of the request. An error on one object is logged and does not prevent sending the other objects.
- The tracking script hook, the storefront routes and the configuration page catch their errors: the page is displayed, `/shopimind/cart-data` returns an empty cart and `/shopimind/spmq` an anonymous visitor.
- An activation or upgrade error, including a PHP error, is logged and reported by Thelia. Table changes already made are kept. A failed activation leaves the module deactivated: activate it again once the cause is fixed. After a failed upgrade, deactivate then activate the module to run the checks again.
- A failed reconnection to ShopiMind after an activation or an upgrade (server unreachable, credentials refused) is logged and does not cancel the operation.
- Uninstallation is never blocked: a failure while dropping a table, deleting the settings or deleting the log files is written to the PHP error log, and the other steps still run.
- Reading the cart for the tag never creates an order in the visitor's session. Without Thelia's tax engine, the tax country is the customer's default address or the default country, then the shop country. When no country is available or a tax cannot be computed (for example a product without a tax rule), cart amounts are sent without tax and the error is logged; the tag is still displayed.

## Logs

When the log level is not *Off*, logs are written to `shopimind.log` in the Thelia log folder (`<thelia_root>/var/log`), otherwise in `<thelia_root>/log` on older installations, or in the `logs` folder of the module when neither is writable. Above 10 MB, the file is renamed `shopimind.log.1`: a single archive is kept. The last lines are shown in the *Log* tab of the configuration page. *Errors only* writes errors, *Info* adds warnings and synchronization activity, *Debug* adds details such as the raw ShopiMind responses.

### Remote access by ShopiMind

When logs are enabled, ShopiMind support can read and clear them with `POST /shopimind/logs`, signed like the other ShopiMind calls:

| Parameter | Description |
|-----------|-------------|
| `testConnection` | Unix timestamp of the call, part of the signed body. Refused beyond 15 minutes of difference with the server clock |
| `action` | `get_logs` (default) or `clear_logs` |
| `lines` | `get_logs` only: number of lines returned from the end of the log (default 500, maximum 5000) |

`get_logs` returns `log_level` (0 off, 1 errors only, 2 info, 3 debug), `file` (file name only), `size` and `archive_size` in bytes, `updated_at`, `lines`, `truncated` (older lines were not returned) and `content`. The archive is read when the current file does not contain enough lines, and at most 1 MB is returned. `clear_logs` empties the file, deletes the archive and returns `cleared_bytes`; the clearing is itself logged.

Log format:
```
[YYYY-MM-DD HH:MM:SS] [LEVEL] [Flow] [Object id=ID] message | ctx={...}
```

## Dependencies

- **ShopiMind SDK**: `shopimind/sdk-shopimind` V2.0.2 (built-in cURL HTTP client, no Guzzle)
- **CustomerFamily module** (optional): customer groups synchronization

## Support

For any questions or assistance:
- **Email**: contact@shopimind.com
- **Documentation**: https://www.shopimind.com
