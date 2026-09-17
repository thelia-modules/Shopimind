<?php
namespace Shopimind\EventListeners;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\TheliaEvents;
use Shopimind\lib\Utils;
use Shopimind\lib\SyncBuffer;

use Thelia\Model\Event\CustomerEvent;
use Thelia\Model\Event\AddressEvent;
use Thelia\Model\Event\NewsletterEvent;
use Thelia\Model\Event\OrderCouponEvent;
use Thelia\Model\Event\OrderEvent;
use Thelia\Model\Event\OrderStatusEvent;
use Thelia\Model\Event\ProductImageEvent;
use Thelia\Model\Event\CategoryEvent;
use Thelia\Model\Event\ProductEvent;
use Thelia\Core\Event\Product\ProductCreateEvent;
use Thelia\Core\Event\Product\ProductUpdateEvent;
use Thelia\Model\Event\BrandEvent;
use Thelia\Model\Event\ProductSaleElementsEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementUpdateEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementCreateEvent;
use Thelia\Model\Event\CouponEvent;
use Thelia\Model\CouponQuery;
use Thelia\Model\Event\ModuleEvent;
use CustomerFamily\Event\CustomerFamilyEvents;
use CustomerFamily\Event\CustomerFamilyEvent;

use Shopimind\EventListeners\CustomersListener;
use Shopimind\EventListeners\CustomersGroupsListener;
use Shopimind\EventListeners\CustomersAddressesListener;
use Shopimind\EventListeners\NewsletterSubscribersListener;
use Shopimind\EventListeners\OrderCouponListener;
use Shopimind\EventListeners\OrderListener;
use Shopimind\EventListeners\OrderStatusListener;
use Shopimind\EventListeners\ProductImagesListener;
use Shopimind\EventListeners\ProductsCategoriesListener;
use Shopimind\EventListeners\ProductsListener;
use Shopimind\EventListeners\ProductsManufacturersListener;
use Shopimind\EventListeners\ProductsVariationsListener;
use Shopimind\EventListeners\VouchersListener;
use Shopimind\EventListeners\OrdersCarriersListener;

/**
 * RT sync event subscriber. Events are not synced inline anymore: they
 * are enqueued into SyncBuffer and flushed once at PHP shutdown, so the
 * same entity modified several times within a request produces a single
 * SDK call. The per-entity logic still lives in the dedicated Listener
 * classes — handlers registered below are thin loops over the buffered
 * entries calling the existing post* static methods.
 *
 * Action names kept separate ('insert' vs 'update') so that a brand-new
 * insert does not lose its insert-specific extras (e.g. variations sync
 * on product create).
 */
class EventListeners implements EventSubscriberInterface
{
    private $dispatcher;

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
        try {
            $this->registerSyncBufferHandlers();
        } catch (\Throwable $e) {
            Utils::handleHookException(static::class . '::__construct', $e);
        }
    }

    /**
     * Magic router: every Symfony EventDispatcher callback declared in
     * getSubscribedEvents (postCustomerInsert, postOrderUpdate, ...) is
     * routed through Utils::safeRun so a fatal in any single listener
     * cannot kill the request. Each handler is implemented as the
     * underscored variant `_postCustomerInsert`...
     */
    public function __call($method, $args)
    {
        $target = '_' . $method;
        if (method_exists($this, $target)) {
            return Utils::safeRun(static::class . '::' . $method, [$this, $target], $args);
        }
        // Never throw from __call: a missing handler must not crash the request.
        // Silently return null — no logging here to avoid any risk of the log
        // path itself faulting.
        return null;
    }

    public static function getSubscribedEvents()
    {
        $parameters = Utils::getParameters();
        $defaultPriority = 128;

        $customersPriority = $parameters['event_priorities']['customers'] ?? $defaultPriority;
        $customersGroupsPriority = $parameters['event_priorities']['customers_groups'] ?? $defaultPriority;
        $custoersAddressesPriority = $parameters['event_priorities']['customers_addresses'] ?? $defaultPriority;
        $newsletterSubscirbersPriority = $parameters['event_priorities']['newsletter_subscribers'] ?? $defaultPriority;
        $orderCouponPriority = $parameters['event_priorities']['order_coupon'] ?? $defaultPriority;
        $orderPriority = $parameters['event_priorities']['orders'] ?? $defaultPriority;
        $orderStatusPriority = $parameters['event_priorities']['orders_status'] ?? $defaultPriority;
        $productImagesPriority = $parameters['event_priorities']['products_images'] ?? $defaultPriority;
        $productCategoriesPriority = $parameters['event_priorities']['products_categories'] ?? $defaultPriority;
        $productPriority = $parameters['event_priorities']['products'] ?? $defaultPriority;
        $brandPriority = $parameters['event_priorities']['products_manufacturers'] ?? $defaultPriority;
        $productsVariationsPriority = $parameters['event_priorities']['products_variations'] ?? $defaultPriority;
        $vouchersPriority = $parameters['event_priorities']['vouchers'] ?? $defaultPriority;
        $ordersCarriersPriority = $parameters['event_priorities']['orders_carriers'] ?? $defaultPriority;

        $eventsListenner = [
            CustomerEvent::POST_INSERT => ['postCustomerInsert', $customersPriority],
            CustomerEvent::POST_UPDATE => ['postCustomerUpdate', $customersPriority],
            CustomerEvent::POST_DELETE => ['postCustomerDelete', $customersPriority],
            AddressEvent::POST_INSERT => ['postAddressInsert', $custoersAddressesPriority],
            AddressEvent::POST_UPDATE => ['postAddressUpdate', $custoersAddressesPriority],
            AddressEvent::POST_DELETE => ['postAddressDelete', $custoersAddressesPriority],
            NewsletterEvent::POST_INSERT => ['postNewsletterInsert', $newsletterSubscirbersPriority],
            NewsletterEvent::POST_UPDATE => ['postNewsletterUpdate', $newsletterSubscirbersPriority],
            NewsletterEvent::POST_DELETE => ['postNewsletterDelete', $newsletterSubscirbersPriority],
            OrderCouponEvent::POST_INSERT => ['postOrderCouponInsert', $orderCouponPriority],
            OrderEvent::POST_INSERT => ['postOrderInsert', $orderPriority],
            OrderEvent::POST_UPDATE => ['postOrderUpdate', $orderPriority],
            OrderEvent::POST_DELETE => ['postOrderDelete', $orderPriority],
            OrderStatusEvent::POST_INSERT => ['postOrderStatusInsert', $orderStatusPriority],
            OrderStatusEvent::POST_UPDATE => ['postOrderStatusUpdate', $orderStatusPriority],
            OrderStatusEvent::POST_DELETE => ['postOrderStatusDelete', $orderStatusPriority],
            ProductImageEvent::POST_INSERT => ['postProductImageInsert', $productImagesPriority],
            ProductImageEvent::POST_UPDATE => ['postProductImageUpdate', $productImagesPriority],
            ProductImageEvent::POST_DELETE => ['postProductImageDelete', $productImagesPriority],
            CategoryEvent::POST_INSERT => ['postCategoryInsert', $productCategoriesPriority],
            CategoryEvent::POST_UPDATE => ['postCategoryUpdate', $productCategoriesPriority],
            CategoryEvent::POST_DELETE => ['postCategoryDelete', $productCategoriesPriority],
            TheliaEvents::PRODUCT_CREATE => ['postProductInsert', $productPriority],
            TheliaEvents::PRODUCT_UPDATE => ['postProductUpdate', $productPriority],
            ProductEvent::POST_DELETE => ['postProductDelete', $productPriority],
            BrandEvent::POST_INSERT => ['postBrandInsert', $brandPriority],
            BrandEvent::POST_UPDATE => ['postBrandUpdate', $brandPriority],
            BrandEvent::POST_DELETE => ['postBrandDelete', $brandPriority],
            TheliaEvents::PRODUCT_ADD_PRODUCT_SALE_ELEMENT => ['postProductSaleElementsInsert', $productsVariationsPriority],
            TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT => ['postProductSaleElementsUpdate', $productsVariationsPriority],
            ProductSaleElementsEvent::POST_DELETE => ['postProductSaleElementsDelete', $productsVariationsPriority],
            CouponEvent::POST_INSERT => ['postCouponInsert', $vouchersPriority],
            CouponEvent::POST_UPDATE => ['postCouponUpdate', $vouchersPriority],
            CouponEvent::POST_DELETE => ['postCouponDelete', $vouchersPriority],
            ModuleEvent::POST_INSERT => ['postModuleInsert', $ordersCarriersPriority],
            ModuleEvent::POST_UPDATE => ['postModuleUpdate', $ordersCarriersPriority],
        ];

        // Classes du module CustomerFamily lues seulement s'il est présent : un module activé puis supprimé
        // du disque ne doit pas empêcher la compilation du conteneur.
        if (class_exists(CustomerFamilyEvents::class) && Utils::isCustomerFamilyActive()) {
            $customersGroupsListener = [
                CustomerFamilyEvents::CUSTOMER_FAMILY_CREATE => ['postCustomerGroupInsert', $customersGroupsPriority],
                CustomerFamilyEvents::CUSTOMER_FAMILY_UPDATE => ['postCustomerGroupUpdate', $customersGroupsPriority],
                CustomerFamilyEvents::CUSTOMER_FAMILY_DELETE => ['postCustomerGroupDelete', $customersGroupsPriority],
            ];
            $eventsListenner = array_merge($eventsListenner, $customersGroupsListener);
        }

        return $eventsListenner;
    }

    /**
     * Register one handler per (type, action) pair. Handlers are thin
     * loops that forward each buffered event to the existing listener
     * static methods.
     *
     * @return void
     */
    protected function registerSyncBufferHandlers()
    {
        $dispatcher = $this->dispatcher;

        // Customers
        SyncBuffer::registerHandler('Customer', 'insert', function ($entries) {
            self::forEachEntry($entries, 'Customer:insert', function ($e) { CustomersListener::postCustomerInsert($e['meta']['event']); });
        });
        SyncBuffer::registerHandler('Customer', 'update', function ($entries) {
            self::forEachEntry($entries, 'Customer:update', function ($e) { CustomersListener::postCustomerUpdate($e['meta']['event']); });
        });
        SyncBuffer::registerHandler('Customer', 'delete', function ($entries) {
            self::forEachEntry($entries, 'Customer:delete', function ($e) { CustomersListener::postCustomerDelete($e['meta']['event']); });
        });

        // Customer Groups
        SyncBuffer::registerHandler('CustomerGroup', 'insert', function ($entries) {
            self::forEachEntry($entries, 'CustomerGroup:insert', function ($e) { CustomersGroupsListener::postCustomerGroupInsert($e['meta']['event']); });
        });
        SyncBuffer::registerHandler('CustomerGroup', 'update', function ($entries) {
            self::forEachEntry($entries, 'CustomerGroup:update', function ($e) { CustomersGroupsListener::postCustomerGroupUpdate($e['meta']['event']); });
        });
        SyncBuffer::registerHandler('CustomerGroup', 'delete', function ($entries) {
            self::forEachEntry($entries, 'CustomerGroup:delete', function ($e) { CustomersGroupsListener::postCustomerGroupDelete($e['meta']['event']); });
        });

        // Customer Addresses
        SyncBuffer::registerHandler('CustomerAddress', 'insert', function ($entries) {
            self::forEachEntry($entries, 'CustomerAddress:insert', function ($e) { CustomersAddressesListener::postAddressInsert($e['meta']['event']); });
        });
        SyncBuffer::registerHandler('CustomerAddress', 'update', function ($entries) {
            self::forEachEntry($entries, 'CustomerAddress:update', function ($e) { CustomersAddressesListener::postAddressUpdate($e['meta']['event']); });
        });
        SyncBuffer::registerHandler('CustomerAddress', 'delete', function ($entries) {
            self::forEachEntry($entries, 'CustomerAddress:delete', function ($e) { CustomersAddressesListener::postAddressDelete($e['meta']['event']); });
        });

        // Newsletter
        SyncBuffer::registerHandler('NewsletterSubscriber', 'insert', function ($entries) {
            self::forEachEntry($entries, 'NewsletterSubscriber:insert', function ($e) { NewsletterSubscribersListener::postNewsletterInsert($e['meta']['event']); });
        });
        SyncBuffer::registerHandler('NewsletterSubscriber', 'update', function ($entries) {
            self::forEachEntry($entries, 'NewsletterSubscriber:update', function ($e) { NewsletterSubscribersListener::postNewsletterUpdate($e['meta']['event']); });
        });

        // Order coupon (no dedup/batching value, but routed through the buffer for consistency)
        SyncBuffer::registerHandler('OrderCoupon', 'insert', function ($entries) {
            self::forEachEntry($entries, 'OrderCoupon:insert', function ($e) { OrderCouponListener::postOrderCouponInsert($e['meta']['event']); });
        });

        // Orders
        SyncBuffer::registerHandler('Order', 'insert', function ($entries) {
            self::forEachEntry($entries, 'Order:insert', function ($e) { OrderListener::postOrderInsert($e['meta']['event']); });
        });
        SyncBuffer::registerHandler('Order', 'update', function ($entries) {
            self::forEachEntry($entries, 'Order:update', function ($e) { OrderListener::postOrderUpdate($e['meta']['event']); });
        });
        SyncBuffer::registerHandler('Order', 'delete', function ($entries) {
            self::forEachEntry($entries, 'Order:delete', function ($e) { OrderListener::postOrderDelete($e['meta']['event']); });
        });

        // Order status
        SyncBuffer::registerHandler('OrderStatus', 'insert', function ($entries) {
            self::forEachEntry($entries, 'OrderStatus:insert', function ($e) { OrderStatusListener::postOrderStatusInsert($e['meta']['event']); });
        });
        SyncBuffer::registerHandler('OrderStatus', 'update', function ($entries) {
            self::forEachEntry($entries, 'OrderStatus:update', function ($e) { OrderStatusListener::postOrderStatusUpdate($e['meta']['event']); });
        });
        SyncBuffer::registerHandler('OrderStatus', 'delete', function ($entries) {
            self::forEachEntry($entries, 'OrderStatus:delete', function ($e) { OrderStatusListener::postOrderStatusDelete($e['meta']['event']); });
        });

        // Product images (dispatcher required by the formatter)
        SyncBuffer::registerHandler('ProductImage', 'insert', function ($entries) use ($dispatcher) {
            self::forEachEntry($entries, 'ProductImage:insert', function ($e) use ($dispatcher) { ProductImagesListener::postProductImageInsert($e['meta']['event'], $dispatcher); });
        });
        SyncBuffer::registerHandler('ProductImage', 'update', function ($entries) use ($dispatcher) {
            self::forEachEntry($entries, 'ProductImage:update', function ($e) use ($dispatcher) { ProductImagesListener::postProductImageUpdate($e['meta']['event'], $dispatcher); });
        });
        SyncBuffer::registerHandler('ProductImage', 'delete', function ($entries) {
            self::forEachEntry($entries, 'ProductImage:delete', function ($e) { ProductImagesListener::postProductImageDelete($e['meta']['event']); });
        });

        // Product categories
        SyncBuffer::registerHandler('ProductCategory', 'insert', function ($entries) {
            self::forEachEntry($entries, 'ProductCategory:insert', function ($e) { ProductsCategoriesListener::postCategoryInsert($e['meta']['event']); });
        });
        SyncBuffer::registerHandler('ProductCategory', 'update', function ($entries) {
            self::forEachEntry($entries, 'ProductCategory:update', function ($e) { ProductsCategoriesListener::postCategoryUpdate($e['meta']['event']); });
        });
        SyncBuffer::registerHandler('ProductCategory', 'delete', function ($entries) {
            self::forEachEntry($entries, 'ProductCategory:delete', function ($e) { ProductsCategoriesListener::postCategoryDelete($e['meta']['event']); });
        });

        // Products (dispatcher required)
        SyncBuffer::registerHandler('Product', 'insert', function ($entries) use ($dispatcher) {
            self::forEachEntry($entries, 'Product:insert', function ($e) use ($dispatcher) { ProductsListener::postProductInsert($e['meta']['event'], $dispatcher); });
        });
        SyncBuffer::registerHandler('Product', 'update', function ($entries) use ($dispatcher) {
            self::forEachEntry($entries, 'Product:update', function ($e) use ($dispatcher) { ProductsListener::postProductUpdate($e['meta']['event'], $dispatcher); });
        });
        SyncBuffer::registerHandler('Product', 'delete', function ($entries) {
            self::forEachEntry($entries, 'Product:delete', function ($e) { ProductsListener::postProductDelete($e['meta']['event']); });
        });

        // Brands / manufacturers
        SyncBuffer::registerHandler('ProductManufacturer', 'insert', function ($entries) {
            self::forEachEntry($entries, 'ProductManufacturer:insert', function ($e) { ProductsManufacturersListener::postBrandInsert($e['meta']['event']); });
        });
        SyncBuffer::registerHandler('ProductManufacturer', 'update', function ($entries) {
            self::forEachEntry($entries, 'ProductManufacturer:update', function ($e) { ProductsManufacturersListener::postBrandUpdate($e['meta']['event']); });
        });
        SyncBuffer::registerHandler('ProductManufacturer', 'delete', function ($entries) {
            self::forEachEntry($entries, 'ProductManufacturer:delete', function ($e) { ProductsManufacturersListener::postBrandDelete($e['meta']['event']); });
        });

        // Products variations (dispatcher required on insert/update)
        SyncBuffer::registerHandler('ProductVariation', 'insert', function ($entries) use ($dispatcher) {
            self::forEachEntry($entries, 'ProductVariation:insert', function ($e) use ($dispatcher) { ProductsVariationsListener::postProductSaleElementsInsert($e['meta']['event'], $dispatcher); });
        });
        SyncBuffer::registerHandler('ProductVariation', 'update', function ($entries) use ($dispatcher) {
            self::forEachEntry($entries, 'ProductVariation:update', function ($e) use ($dispatcher) { ProductsVariationsListener::postProductSaleElementsUpdate($e['meta']['event'], $dispatcher); });
        });
        SyncBuffer::registerHandler('ProductVariation', 'delete', function ($entries) {
            self::forEachEntry($entries, 'ProductVariation:delete', function ($e) { ProductsVariationsListener::postProductSaleElementsDelete($e['meta']['event']); });
        });

        // Vouchers
        SyncBuffer::registerHandler('Voucher', 'insert', function ($entries) {
            self::forEachEntry($entries, 'Voucher:insert', function ($e) { VouchersListener::postCouponInsert($e['meta']['event']); });
        });
        SyncBuffer::registerHandler('Voucher', 'update', function ($entries) {
            self::forEachEntry($entries, 'Voucher:update', function ($e) {
                VouchersListener::syncCoupon(isset($e['meta']['event']) ? $e['meta']['event']->getModel() : $e['meta']['coupon'], 'Update');
            });
        });
        SyncBuffer::registerHandler('Voucher', 'delete', function ($entries) {
            self::forEachEntry($entries, 'Voucher:delete', function ($e) { VouchersListener::postCouponDelete($e['meta']['event']); });
        });

        // Transporteurs (modules de livraison) : un seul envoi pour tous les modules de la requête
        SyncBuffer::registerHandler('OrderCarrier', 'upsert', function ($entries) {
            OrdersCarriersListener::syncCarriers(array_column($entries, 'id'));
        });
    }

    /**
     * Envoie chaque entité du tampon séparément : l'erreur d'une entité est journalisée sans bloquer les suivantes.
     *
     * @param array    $entries
     * @param string   $group
     * @param callable $send
     */
    protected static function forEachEntry(array $entries, string $group, callable $send)
    {
        foreach ($entries as $entry) {
            try {
                $send($entry);
            } catch (\Throwable $e) {
                Utils::logException('RT', $group, $e, (isset($entry['id']) && is_scalar($entry['id'])) ? $entry['id'] : null);
            }
        }
    }

    /**
     * Resolve an id from an event, falling back to a sentinel when the
     * event doesn't carry one we can use. The id only needs to dedup
     * within the same request so a spl_object_id fallback is acceptable.
     *
     * @param mixed $maybeId
     * @param object $event
     * @return string|int
     */
    protected static function idOrObjectHash($maybeId, $event)
    {
        if (!empty($maybeId)) {
            return $maybeId;
        }
        return 'obj:' . spl_object_hash($event);
    }

    /**
     * CustomerFamilyEvent (module CustomerFamily) n'a pas de getModel() : l'appel levait une erreur,
     * interceptée par safeRun, et aucun groupe client n'était synchronisé en temps réel.
     *
     * @param CustomerFamilyEvent $event
     * @return int|null
     */
    protected static function customerFamilyId(CustomerFamilyEvent $event)
    {
        $customerFamily = $event->getCustomerFamily();

        return $customerFamily ? $customerFamily->getId() : null;
    }

    /**
     * Customer
     */
    public function _postCustomerInsert(CustomerEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            SyncBuffer::enqueue('Customer', 'insert', self::idOrObjectHash($event->getModel()->getId(), $event), ['event' => $event]);
        }
    }

    public function _postCustomerUpdate(CustomerEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            SyncBuffer::enqueue('Customer', 'update', self::idOrObjectHash($event->getModel()->getId(), $event), ['event' => $event]);
        }
    }

    public function _postCustomerDelete(CustomerEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            SyncBuffer::enqueue('Customer', 'delete', self::idOrObjectHash($event->getModel()->getId(), $event), ['event' => $event]);
        }
    }

    /**
     * Customer groups
     */
    public function _postCustomerGroupInsert(CustomerFamilyEvent $event)
    {
        if (Utils::useRealTimeSynchronization() && Utils::isCustomerFamilyActive()) {
            SyncBuffer::enqueue('CustomerGroup', 'insert', self::idOrObjectHash(self::customerFamilyId($event), $event), ['event' => $event]);
        }
    }

    public function _postCustomerGroupUpdate(CustomerFamilyEvent $event)
    {
        if (Utils::useRealTimeSynchronization() && Utils::isCustomerFamilyActive()) {
            SyncBuffer::enqueue('CustomerGroup', 'update', self::idOrObjectHash(self::customerFamilyId($event), $event), ['event' => $event]);
        }
    }

    public function _postCustomerGroupDelete(CustomerFamilyEvent $event)
    {
        if (Utils::useRealTimeSynchronization() && Utils::isCustomerFamilyActive()) {
            SyncBuffer::enqueue('CustomerGroup', 'delete', self::idOrObjectHash(self::customerFamilyId($event), $event), ['event' => $event]);
        }
    }

    /**
     * Customer addresses
     */
    public function _postAddressInsert(AddressEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            SyncBuffer::enqueue('CustomerAddress', 'insert', self::idOrObjectHash($event->getModel()->getId(), $event), ['event' => $event]);
        }
    }

    public function _postAddressUpdate(AddressEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            SyncBuffer::enqueue('CustomerAddress', 'update', self::idOrObjectHash($event->getModel()->getId(), $event), ['event' => $event]);
        }
    }

    public function _postAddressDelete(AddressEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            SyncBuffer::enqueue('CustomerAddress', 'delete', self::idOrObjectHash($event->getModel()->getId(), $event), ['event' => $event]);
        }
    }

    /**
     * Newsletter
     */
    public function _postNewsletterInsert(NewsletterEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            SyncBuffer::enqueue('NewsletterSubscriber', 'insert', self::idOrObjectHash($event->getModel()->getId(), $event), ['event' => $event]);
        }
    }

    public function _postNewsletterUpdate(NewsletterEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            SyncBuffer::enqueue('NewsletterSubscriber', 'update', self::idOrObjectHash($event->getModel()->getId(), $event), ['event' => $event]);
        }
    }

    public function _postNewsletterDelete(NewsletterEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            // Historical behaviour mapped delete → update (no dedicated delete path).
            SyncBuffer::enqueue('NewsletterSubscriber', 'update', self::idOrObjectHash($event->getModel()->getId(), $event), ['event' => $event]);
        }
    }

    /**
     * Order coupon
     */
    public function _postOrderCouponInsert(OrderCouponEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            SyncBuffer::enqueue('OrderCoupon', 'insert', self::idOrObjectHash($event->getModel()->getId(), $event), ['event' => $event]);

            // Un bon à usage par client est consommé sans que le coupon soit enregistré (seul le compteur du client
            // change) : aucun événement Coupon n'est émis. Il est renvoyé ici pour mettre is_used à jour.
            $coupon = CouponQuery::create()->findOneByCode($event->getModel()->getCode());
            if (null !== $coupon) {
                SyncBuffer::enqueue('Voucher', 'update', $coupon->getId(), ['coupon' => $coupon]);
            }
        }
    }

    /**
     * Orders
     */
    public function _postOrderInsert(OrderEvent $event)
    {
        // Envoyées même sans synchronisation temps réel, mais jamais sans identifiants API valides.
        if (Utils::isConnected()) {
            SyncBuffer::enqueue('Order', 'insert', self::idOrObjectHash($event->getModel()->getId(), $event), ['event' => $event]);
        }
    }

    public function _postOrderUpdate(OrderEvent $event)
    {
        if (Utils::isConnected()) {
            SyncBuffer::enqueue('Order', 'update', self::idOrObjectHash($event->getModel()->getId(), $event), ['event' => $event]);
        }
    }

    public function _postOrderDelete(OrderEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            SyncBuffer::enqueue('Order', 'delete', self::idOrObjectHash($event->getModel()->getId(), $event), ['event' => $event]);
        }
    }

    /**
     * Order status
     */
    public function _postOrderStatusInsert(OrderStatusEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            SyncBuffer::enqueue('OrderStatus', 'insert', self::idOrObjectHash($event->getModel()->getId(), $event), ['event' => $event]);
        }
    }

    public function _postOrderStatusUpdate(OrderStatusEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            SyncBuffer::enqueue('OrderStatus', 'update', self::idOrObjectHash($event->getModel()->getId(), $event), ['event' => $event]);
        }
    }

    public function _postOrderStatusDelete(OrderStatusEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            SyncBuffer::enqueue('OrderStatus', 'delete', self::idOrObjectHash($event->getModel()->getId(), $event), ['event' => $event]);
        }
    }

    /**
     * Product images
     */
    public function _postProductImageInsert(ProductImageEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            SyncBuffer::enqueue('ProductImage', 'insert', self::idOrObjectHash($event->getModel()->getId(), $event), ['event' => $event]);
        }
    }

    public function _postProductImageUpdate(ProductImageEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            SyncBuffer::enqueue('ProductImage', 'update', self::idOrObjectHash($event->getModel()->getId(), $event), ['event' => $event]);
        }
    }

    public function _postProductImageDelete(ProductImageEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            SyncBuffer::enqueue('ProductImage', 'delete', self::idOrObjectHash($event->getModel()->getId(), $event), ['event' => $event]);
        }
    }

    /**
     * Product categories
     */
    public function _postCategoryInsert(CategoryEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            SyncBuffer::enqueue('ProductCategory', 'insert', self::idOrObjectHash($event->getModel()->getId(), $event), ['event' => $event]);
        }
    }

    public function _postCategoryUpdate(CategoryEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            SyncBuffer::enqueue('ProductCategory', 'update', self::idOrObjectHash($event->getModel()->getId(), $event), ['event' => $event]);
        }
    }

    public function _postCategoryDelete(CategoryEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            SyncBuffer::enqueue('ProductCategory', 'delete', self::idOrObjectHash($event->getModel()->getId(), $event), ['event' => $event]);
        }
    }

    /**
     * Products
     */
    public function _postProductInsert(ProductCreateEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            SyncBuffer::enqueue('Product', 'insert', self::idOrObjectHash($event->getProduct()->getId(), $event), ['event' => $event]);
        }
    }

    public function _postProductUpdate(ProductUpdateEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            SyncBuffer::enqueue('Product', 'update', self::idOrObjectHash($event->getProduct()->getId(), $event), ['event' => $event]);
        }
    }

    public function _postProductDelete(ProductEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            SyncBuffer::enqueue('Product', 'delete', self::idOrObjectHash($event->getModel()->getId(), $event), ['event' => $event]);
        }
    }

    /**
     * Brands / manufacturers
     */
    public function _postBrandInsert(BrandEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            SyncBuffer::enqueue('ProductManufacturer', 'insert', self::idOrObjectHash($event->getModel()->getId(), $event), ['event' => $event]);
        }
    }

    public function _postBrandUpdate(BrandEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            SyncBuffer::enqueue('ProductManufacturer', 'update', self::idOrObjectHash($event->getModel()->getId(), $event), ['event' => $event]);
        }
    }

    public function _postBrandDelete(BrandEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            SyncBuffer::enqueue('ProductManufacturer', 'delete', self::idOrObjectHash($event->getModel()->getId(), $event), ['event' => $event]);
        }
    }

    /**
     * Products variations
     */
    public function _postProductSaleElementsInsert(ProductSaleElementCreateEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            $id = method_exists($event, 'getProductSaleElement') && $event->getProductSaleElement() ? $event->getProductSaleElement()->getId() : null;
            SyncBuffer::enqueue('ProductVariation', 'insert', self::idOrObjectHash($id, $event), ['event' => $event]);
        }
    }

    public function _postProductSaleElementsUpdate(ProductSaleElementUpdateEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            $id = method_exists($event, 'getProductSaleElementId') ? $event->getProductSaleElementId() : null;
            SyncBuffer::enqueue('ProductVariation', 'update', self::idOrObjectHash($id, $event), ['event' => $event]);
        }
    }

    public function _postProductSaleElementsDelete(ProductSaleElementsEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            SyncBuffer::enqueue('ProductVariation', 'delete', self::idOrObjectHash($event->getModel()->getId(), $event), ['event' => $event]);
        }
    }

    /**
     * Coupons
     */
    public function _postCouponInsert(CouponEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            SyncBuffer::enqueue('Voucher', 'insert', self::idOrObjectHash($event->getModel()->getId(), $event), ['event' => $event]);
        }
    }

    public function _postCouponUpdate(CouponEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            SyncBuffer::enqueue('Voucher', 'update', self::idOrObjectHash($event->getModel()->getId(), $event), ['event' => $event]);
        }
    }

    public function _postCouponDelete(CouponEvent $event)
    {
        if (Utils::useRealTimeSynchronization()) {
            SyncBuffer::enqueue('Voucher', 'delete', self::idOrObjectHash($event->getModel()->getId(), $event), ['event' => $event]);
        }
    }

    /**
     * Transporteurs : un transporteur Thelia est un module de livraison. ModuleEvent::POST_UPDATE est émis à
     * chaque sauvegarde d'un module, y compris à chaque rafraîchissement de la liste des modules : les modules
     * qui ne sont pas de livraison sont écartés ici, les doublons par le tampon. La suppression n'est pas
     * transmise, et Thelia refuse de supprimer un module utilisé par une commande.
     */
    public function _postModuleInsert(ModuleEvent $event)
    {
        $this->enqueueCarrier($event);
    }

    public function _postModuleUpdate(ModuleEvent $event)
    {
        $this->enqueueCarrier($event);
    }

    protected function enqueueCarrier(ModuleEvent $event)
    {
        $module = $event->getModel();
        if (Utils::useRealTimeSynchronization() && !empty($module)
            && (int) $module->getType() === \Thelia\Module\BaseModule::DELIVERY_MODULE_TYPE) {
            SyncBuffer::enqueue('OrderCarrier', 'upsert', $module->getId());
        }
    }
}
