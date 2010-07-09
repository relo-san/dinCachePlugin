dinCachePlugin for Symfony 1.3/1.4
==================================

For doc's in russian (primary) - see README-ru.md

Plugin info
-----------
This plugin features so-called "smart caching" method.

Its main advantage: cache is purged only if original data has changed. To achieve this, plugin uses
a set of rules, similar to routing. Rule describes all data dependencies, paths and keys creation,
choice of caching method etc. Cache manager works as proxy – it processes all data requests and
then extracts data from cache or forwards query to ORM.

Once cache manager receives a data request with a certain key (rule name), it checks if there’s
relevant data in cache. If data is found, it is return. If not – manager forwards request to ORM
model, received data is processed according to its type and stored in cache. Any model change
(object is added, deleted or modified) is intercepted by special listener. Manager processes this
event and – if suitable rule is found – purges all associated with that object cached data.

Key advantages
--------------
* Plugin is light and saves a lot of resources, eliminating the need to load an ORM, if cached data is found
* Lifespan of cache is limited only by it’s relevance – until original data won’t be changed partially or completely
* Flexible cache management through yml configuration file

Current limitations
-------------------
* Only ORM Doctrine 1.2 currently supported
* Only file caching is tested at this moment

Dependencies
------------
* [Symfony](http://www.symfony-project.org/) 1.3/1.4
* [Doctrine](http://www.doctrine-project.org/) 1.2

Installation
------------
For convenience manager is made accessible through sfContext.

To do this you have add three string to setup() method in ProjectConfiguration.class.php:

    $pldir = sfConfig::get( 'sf_plugins_dir' );
    require_once( $pldir . '/dinCachePlugin/lib/config/dinCacheRoutingConfigHandler.php' );
    require_once( $pldir . '/dinCachePlugin/lib/config/dinFactoryConfigHandler.php' );

Also you need to include another method there to add the listener:

    public function configureDoctrineConnection( Doctrine_Connection $conn )
    {
        $conn->addRecordListener( new dinCacheDoctrineListener( $conn ) );
    }

*When using dinSymfonyExtraPlugin abovementioned operations are not necessary.*

Then you have to edit (or create empty one) config_handlers.yml file in config folder of your
application, by replacing (or adding) factory configuration handler:

    'config/factories.yml':
        class:          'dinFactoryConfigHandler'

and cache manager configuration handler:

    'config/cache_routing.yml':
        class:          'dinCacheRoutingConfigHandler'

In your application’s configuration file factories.yml add this block:

    cache_manager:
        class:                  'dinCacheManager'
        param:
            load_configuration: true
            defaults:
                ttl:            '157680000'
                driver:         'sfFileCache'
                ipf:            '1'

*It’s recommended to add it for all environments (all)*

*For high loading projects you may set option **load_configuration** in false. In this case
routes configuration loaded when the first request to the manager, not during initialization*

*For other cache drivers you may set other extended default options, for example info for access
to memcached server(s)*

This ends manager installation.

Setup
-----
Plugin has basic defaults for number of data types: data, page and choices. These types are
necessary for normal data processing.

Manager is familiar with following data types:

1. **data** – stores one object by ID. If *ipf* parameter in rule is set to number other than 1,
manager will store indicated number of objects in one storage unit (file). It also returns object
data as a data array of a single object, not as a array of objects (arrays).

2. **page** - caches pages of data.

3. **choices** - intended for caching value lists (selected items, for example). For this data type
only id/title is stored.

4. **custom** - for caching of custom data set.

5. **prepared** - for caching of prepared data. It receives data from a model and relays it "as is",
without processing, then stores in cache. For all other data types manager requests Doctrine_Query
object from a model and picks data by himself.

Now you can take some time to write rules for caching of needed data and model linking.

First of all, you’ll need to create cache_routing.yml file in project’s config folder. It has two
sections: **routes** и **links**.

Here’s an example of standard rule:

    routes:
        rule_name:
            type:           'custom' #or others: data, page, choices etc.
            get:
                model:      'model_name'
                method:     'method_name'
                path:       ':_root/model_name/route_name'
                key:        ':field_name.:other_field_name.:_i18n.cache'
            remove:
                default:
                    path:   ':_root/model_name/route_name'
                    key:    ':field_name.:other_field_name.*.cache'

Rules syntax:

**rule_name** - rule name, serves as a key for data query.

**get** - this section describes rules for cache storing and fetching.

**model** и **method** - indicate, what model and method to use fetch original data.

**path** - path to store a cache (only for sfFileCache driver).

Path and Key strings can include following substitutes:

**:_model** - current model name.

**:_type** - data type (data, page, choices etc.)

**:_root** - root folder for cache data ({project}/cache/data by default).

**:_i18n** - if model have i18n behavior, this variable returns current language.

Path and key strings can also include custom field name which serves as a key. Actually, this
string is used for cache search and purging.

Nonnumeric keys are converted to md5 hashes, numerical keys are stored in 3 subfolders, to avoid
having more than a 10 000 cached files in one folder.

**remove** - describes cache purging rules.

Path and key strings may contain "*" wildcard, which represents any number of fields.

You can use an actual model name instead of default. It might be useful, because different models
may have different paths and keys (e.g. there is no category_id key in regular category model, but
id key, which means the same, essentially).

Additionally in get section you can use these keys:

**_no_prepare_translations** - this switch indicates that there’s no need to relocate translations
from multidimensional array (in this case objects with i18n will have a key Translation with
translation array). Switch is activated by its presence, actual value doesn’t matter, so set it to
"true".

**join** - only for choices data type, gets you a multidimensional list in "category/object" form.
Valid values for this item are foreign key aliases of basic model.

**ttl** - cache time-to-live in seconds (default: 157680000 – one year).

**driver** - cache driver (default: sfFileCache, but you can use any cache driver, available in
Symfony). Not tested yet!

**ipf** - only for data type, allows you to store exact number of objects in one storage (file).

Thus models are linked to rules:

    links:
        model_name:     ['route_name']

If model is not linked, respective cache won’t be purged if original data has changed.

If basic model, source of original data, won’t be linked to rule, you’ll get an exception.

Few words about requirements for methods used to fetch a data. All methods are required to receive,
as first argument, a set of handles for data fetching. All methods, except rules like prepared are
required to return object Doctrine_Query. For prepared data array or object/collection must be
returned.

Here’s an example for fetch method (placed, of course, in a table class of an appropriate model):

    public function method_name( $params )
    {
        $q = $this->createQuery();

        *** conditions with data from $params etc.

        return $q;
    }

Use
---
At controller you can address cache manager like this:

    $data = $this->getContext()->get( 'cache_manager' )->getContent(
        'rule_name', 'model_name', array( 'field_name' => value )
    );

*When using dinSymfonyExtraPlugin manager object is already present at the controller*

That’s all, folks :)

To do
-----
1. Add support for Propel ORM
2. Add support for custom data sources
3. Convert plugin to a service
4. Test all
5. Create PEAR package for Symfony channel
6. Turn plugin into a independent component for use without Symfony framework and also in Symfony 2