<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopimind\EventListeners;

use Shopimind\lib\Utils;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Product\ProductCreateEvent;
use Thelia\Core\Event\Product\ProductUpdateEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementCreateEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Event\AddressEvent;
use Thelia\Model\Event\BrandEvent;
use Thelia\Model\Event\CategoryEvent;
use Thelia\Model\Event\CouponEvent;
use Thelia\Model\Event\CustomerEvent;
use Thelia\Model\Event\NewsletterEvent;
use Thelia\Model\Event\OrderCouponEvent;
use Thelia\Model\Event\OrderEvent;
use Thelia\Model\Event\OrderStatusEvent;
use Thelia\Model\Event\ProductEvent;
use Thelia\Model\Event\ProductImageEvent;
use Thelia\Model\Event\ProductSaleElementsEvent;

class EventListeners implements EventSubscriberInterface
{
    private EventDispatcherInterface $dispatcher;

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        $parameters = Utils::getParameters();
        $defaultPriority = 128;

        $customersPriority = $parameters['event_priorities']['customers'] ?? $defaultPriority;
        $customersAddressesPriority = $parameters['event_priorities']['customers_addresses'] ?? $defaultPriority;
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
            AddressEvent::POST_INSERT => ['postAddressInsert', $customersAddressesPriority],
            AddressEvent::POST_UPDATE => ['postAddressUpdate', $customersAddressesPriority],
            AddressEvent::POST_DELETE => ['postAddressDelete', $customersAddressesPriority],
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
     * Event listener for customer-insert actions.
     *
     * @param CustomerEvent $event
     * @return void
     */
    public function postCustomerInsert(CustomerEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            CustomersListener::postCustomerInsert($event);
        }
    }

    /**
     * Event listener for customer-update actions.
     *
     * @param CustomerEvent $event
     * @return void
     */
    public function postCustomerUpdate(CustomerEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            CustomersListener::postCustomerUpdate($event);
        }
    }

    /**
     * Event listener for customer-delete actions.
     *
     * @param CustomerEvent $event
     * @return void
     */
    public function postCustomerDelete(CustomerEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            CustomersListener::postCustomerDelete($event);
        }
    }

    /**
     * Event listener for customers-addresses-insert actions.
     *
     * @param AddressEvent $event
     * @return void
     */
    public function postAddressInsert(AddressEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            CustomersAddressesListener::postAddressInsert($event);
        }
    }

    /**
     * Event listener for customers-addresses-update actions.
     *
     * @param AddressEvent $event
     * @return void
     */
    public function postAddressUpdate(AddressEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            CustomersAddressesListener::postAddressUpdate($event);
        }
    }

    /**
     * Event listener for customers-addresses-delete actions.
     *
     * @param AddressEvent $event
     * @return void
     */
    public function postAddressDelete(AddressEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            CustomersAddressesListener::postAddressDelete($event);
        }
    }

    /**
     * Event listener for newsletter-insert actions.
     *
     * @param NewsletterEvent $event
     * @return void
     */
    public function postNewsletterInsert(NewsletterEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            NewsletterSubscribersListener::postNewsletterInsert($event);
        }
    }

    /**
     * Event listener for newsletter-update actions.
     *
     * @param NewsletterEvent $event
     * @return void
     */
    public function postNewsletterUpdate(NewsletterEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            NewsletterSubscribersListener::postNewsletterUpdate($event);
        }
    }

    /**
     * Event listener for newsletter-delete actions.
     *
     * @param NewsletterEvent $event
     * @return void
     */
    public function postNewsletterDelete(NewsletterEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            NewsletterSubscribersListener::postNewsletterUpdate($event);
        }
    }

    /**
     * Event listener for order-coupon-insert actions.
     *
     * @param OrderCouponEvent $event
     * @return void
     */
    public function postOrderCouponInsert(OrderCouponEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            OrderCouponListener::postOrderCouponInsert($event);
        }
    }

    /**
     * Event listener for order-insert actions.
     *
     * @param OrderEvent $event
     * @return void
     */
    public function postOrderInsert(OrderEvent $event): void
    {
        OrderListener::postOrderInsert($event);
    }

    /**
     * Event listener for order-update actions.
     *
     * @param OrderEvent $event
     * @return void
     */
    public function postOrderUpdate(OrderEvent $event): void
    {
        OrderListener::postOrderUpdate($event);
    }

    /**
     * Event listener for order-delete actions.
     *
     * @param OrderEvent $event
     * @return void
     */
    public function postOrderDelete(OrderEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            OrderListener::postOrderDelete($event);
        }
    }

    /**
     * Event listener for order-status-insert actions.
     *
     * @param OrderStatusEvent $event
     * @return void
     */
    public function postOrderStatusInsert(OrderStatusEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            OrderStatusListener::postOrderStatusInsert($event);
        }
    }

    /**
     * Event listener for order-status-update actions.
     *
     * @param OrderStatusEvent $event
     * @return void
     */
    public function postOrderStatusUpdate(OrderStatusEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            OrderStatusListener::postOrderStatusUpdate($event);
        }
    }

    /**
     * Event listener for order-status-delete actions.
     *
     * @param OrderStatusEvent $event
     * @return void
     */
    public function postOrderStatusDelete(OrderStatusEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            OrderStatusListener::postOrderStatusDelete($event);
        }
    }

    /**
     * Event listener for product-images-insert actions.
     *
     * @param ProductImageEvent $event
     * @return void
     */
    public function postProductImageInsert(ProductImageEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            ProductImagesListener::postProductImageInsert($event, $this->dispatcher);
        }
    }

    /**
     * Event listener for product-images-update actions.
     *
     * @param ProductImageEvent $event
     * @return void
     */
    public function postProductImageUpdate(ProductImageEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            ProductImagesListener::postProductImageUpdate($event, $this->dispatcher);
        }
    }

    /**
     * Event listener for product-images-delete actions.
     *
     * @param ProductImageEvent $event
     * @return void
     */
    public function postProductImageDelete(ProductImageEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            ProductImagesListener::postProductImageDelete($event);
        }
    }

    /**
     * Event listener for product-categories-insert actions.
     *
     * @param CategoryEvent $event
     * @return void
     */
    public function postCategoryInsert(CategoryEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            ProductsCategoriesListener::postCategoryInsert($event);
        }
    }

    /**
     * Event listener for product-categories-update actions.
     *
     * @param CategoryEvent $event
     * @return void
     */
    public function postCategoryUpdate(CategoryEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            ProductsCategoriesListener::postCategoryUpdate($event);
        }
    }

    /**
     * Event listener for product-categories-delete actions.
     *
     * @param CategoryEvent $event
     * @return void
     */
    public function postCategoryDelete(CategoryEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            ProductsCategoriesListener::postCategoryDelete($event);
        }
    }

    /**
     * Event listener for products-insert actions.
     *
     * @param ProductCreateEvent $event
     * @return void
     */
    public function postProductInsert(ProductCreateEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            ProductsListener::postProductInsert($event, $this->dispatcher);
        }
    }

    /**
     * Event listener for products-update actions.
     *
     * @param ProductUpdateEvent $event
     * @return void
     */
    public function postProductUpdate(ProductUpdateEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            ProductsListener::postProductUpdate($event, $this->dispatcher);
        }
    }

    /**
     * Event listener for products-delete actions.
     *
     * @param ProductEvent $event
     * @return void
     */
    public function postProductDelete(ProductEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            ProductsListener::postProductDelete($event);
        }
    }

    /**
     * Event listener for brand-insert actions.
     *
     * @param BrandEvent $event
     * @return void
     */
    public function postBrandInsert(BrandEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            ProductsManufacturersListener::postBrandInsert($event);
        }
    }

    /**
     * Event listener for brand-update actions.
     *
     * @param BrandEvent $event
     * @return void
     */
    public function postBrandUpdate(BrandEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            ProductsManufacturersListener::postBrandUpdate($event);
        }
    }

    /**
     * Event listener for brand-delete actions.
     *
     * @param BrandEvent $event
     * @return void
     */
    public function postBrandDelete(BrandEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            ProductsManufacturersListener::postBrandDelete($event);
        }
    }

    /**
     * Event listener for products-variations-insert actions.
     *
     * @param ProductSaleElementCreateEvent $event
     * @return void
     */
    public function postProductSaleElementsInsert(ProductSaleElementCreateEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            ProductsVariationsListener::postProductSaleElementsInsert($event, $this->dispatcher);
        }
    }

    /**
     * Event listener for products-variations-update actions.
     *
     * @param ProductSaleElementUpdateEvent $event
     * @return void
     */
    public function postProductSaleElementsUpdate(ProductSaleElementUpdateEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            ProductsVariationsListener::postProductSaleElementsUpdate($event, $this->dispatcher);
        }
    }

    /**
     * Event listener for products-variations-delete actions.
     *
     * @param ProductSaleElementsEvent $event
     * @return void
     */
    public function postProductSaleElementsDelete(ProductSaleElementsEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            ProductsVariationsListener::postProductSaleElementsDelete($event);
        }
    }

    /**
     * Event listener for coupon-insert actions.
     *
     * @param CouponEvent $event
     * @return void
     */
    public function postCouponInsert(CouponEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            VouchersListener::postCouponInsert($event);
        }
    }

    /**
     * Event listener for coupon-update actions.
     *
     * @param CouponEvent $event
     * @return void
     */
    public function postCouponUpdate(CouponEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            VouchersListener::postCouponUpdate($event);
        }
    }

    /**
     * Event listener for coupon-delete actions.
     *
     * @param CouponEvent $event
     * @return void
     */
    public function postCouponDelete(CouponEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            VouchersListener::postCouponDelete($event);
        }
    }
}
