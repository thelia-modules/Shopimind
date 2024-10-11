<?php
namespace Shopimind\EventListeners;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\TheliaEvents;
use Shopimind\lib\Utils;

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

use Shopimind\EventListeners\CustomersListener;
use Shopimind\EventListeners\CustomersAddressesListener;
use Shopimind\EventListeners\NewsletterSubscribersListener;
use Shopimind\EventListeners\OrderCouponListener;
use Shopimind\EventListeners\OrderListener;
use Shopimind\EventListeners\OrderStatusListener;
use Shopimind\EventListeners\ProductImagesListener;
use Shopimind\EventListeners\ProductsCategoriesListener;
use Shopimind\EventListeners\ProductsManufacturersListener;
use Shopimind\EventListeners\ProductsVariationsListener;
use Shopimind\EventListeners\VouchersListener;

class EventListeners implements EventSubscriberInterface
{
    private $dispatcher;

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public static function getSubscribedEvents()
    {
        $parameters = Utils::getParameters();
        $defaultPriority = 128;

        $customersPriority = $parameters['event_priorities']['customers'] ?? $defaultPriority;
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

        return [
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
        ];
    }

    /**
     * Event listener for customer-related actions.
     * 
     */
    public function postCustomerInsert(CustomerEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            CustomersListener::postCustomerInsert( $event );
        }
    }

    public function postCustomerUpdate(CustomerEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            CustomersListener::postCustomerUpdate( $event );
        }
    }

    public function postCustomerDelete(CustomerEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            CustomersListener::postCustomerDelete( $event );
        }
    }

    /**
     * Event listener for customers-addresses-related actions.
     * 
     */
    public function postAddressInsert(AddressEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            CustomersAddressesListener::postAddressInsert( $event );
        }
    }

    public function postAddressUpdate(AddressEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            CustomersAddressesListener::postAddressUpdate( $event );
        }
    }

    public function postAddressDelete(AddressEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            CustomersAddressesListener::postAddressDelete( $event );
        }
    }

    /**
     * Event listener for newsletter-related actions.
     * 
     */
    public function postNewsletterInsert(NewsletterEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            NewsletterSubscribersListener::postNewsletterInsert( $event );
        }
    }

    public function postNewsletterUpdate(NewsletterEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            NewsletterSubscribersListener::postNewsletterUpdate( $event );
        }
    }

    public function postNewsletterDelete(NewsletterEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            NewsletterSubscribersListener::postNewsletterUpdate( $event );
        }
    }

    /**
     * Event listener for order-coupon-related actions.
     * 
     */
    public function postOrderCouponInsert(OrderCouponEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            OrderCouponListener::postOrderCouponInsert( $event );
        }
    }

    /**
     * Event listener for order-related actions.
     * 
     */
    public function postOrderInsert(OrderEvent $event)
    {
        OrderListener::postOrderInsert( $event );
    }

    public function postOrderUpdate(OrderEvent $event)
    {
        OrderListener::postOrderUpdate( $event );
    }

    public function postOrderDelete(OrderEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            OrderListener::postOrderDelete( $event );
        }
    }

    /**
     * Event listener for order-status-related actions.
     * 
     */
    public function postOrderStatusInsert(OrderStatusEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            OrderStatusListener::postOrderStatusInsert( $event );
        }
    }

    public function postOrderStatusUpdate(OrderStatusEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            OrderStatusListener::postOrderStatusUpdate( $event );
        }
    }

    public function postOrderStatusDelete(OrderStatusEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            OrderStatusListener::postOrderStatusDelete( $event );
        }
    }

    /**
     * Event listener for product-images-related actions.
     * 
     */
    public function postProductImageInsert(ProductImageEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            ProductImagesListener::postProductImageInsert( $event, $this->dispatcher );
        }
    }

    public function postProductImageUpdate(ProductImageEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            ProductImagesListener::postProductImageUpdate( $event, $this->dispatcher );
        }
    }

    public function postProductImageDelete(ProductImageEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            ProductImagesListener::postProductImageDelete( $event );
        }
    }

    /**
     * Event listener for product-categories-related actions.
     * 
     */
    public function postCategoryInsert(CategoryEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            ProductsCategoriesListener::postCategoryInsert( $event );
        }
    }

    public function postCategoryUpdate(CategoryEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            ProductsCategoriesListener::postCategoryUpdate( $event );
        }
    }

    public function postCategoryDelete(CategoryEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            ProductsCategoriesListener::postCategoryDelete( $event );
        }
    } 

    /**
     * Event listener for products-related actions.
     * 
     */
    public function postProductInsert(ProductCreateEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            ProductsListener::postProductInsert( $event, $this->dispatcher );
        }
    }

    public function postProductUpdate(ProductUpdateEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            ProductsListener::postProductUpdate( $event, $this->dispatcher );
        }
    }

    public function postProductDelete(ProductEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            ProductsListener::postProductDelete( $event );
        }
    } 

    /**
     * Event listener for products-manufacturers-related actions.
     * 
     */
    public function postBrandInsert(BrandEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            ProductsManufacturersListener::postBrandInsert( $event );
        }
    }

    public function postBrandUpdate(BrandEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            ProductsManufacturersListener::postBrandUpdate( $event );
        }
    }

    public function postBrandDelete(BrandEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            ProductsManufacturersListener::postBrandDelete( $event );
        }
    } 

    /**
     * Event listener for products-variations-related actions.
     * 
     */
    public function postProductSaleElementsInsert(ProductSaleElementCreateEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            ProductsVariationsListener::postProductSaleElementsInsert( $event, $this->dispatcher );
        }
    }

    public function postProductSaleElementsUpdate(ProductSaleElementUpdateEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            ProductsVariationsListener::postProductSaleElementsUpdate( $event, $this->dispatcher );
        }
    }

    public function postProductSaleElementsDelete(ProductSaleElementsEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            ProductsVariationsListener::postProductSaleElementsDelete( $event );
        }
    } 

     /**
     * Event listener for coupon-related actions.
     * 
     */
    public function postCouponInsert(CouponEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            VouchersListener::postCouponInsert( $event );
        }
    }

    public function postCouponUpdate(CouponEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            VouchersListener::postCouponUpdate( $event );
        }
    }

    public function postCouponDelete(CouponEvent $event)
    {
        if ( Utils::useRealTimeSynchronization() ){
            VouchersListener::postCouponDelete( $event );
        }
    } 
}
