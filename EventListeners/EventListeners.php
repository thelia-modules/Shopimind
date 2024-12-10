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

use CustomerFamily\Event\CustomerFamilyEvent;
use CustomerFamily\Event\CustomerFamilyEvents;
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
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private CustomersAddressesListener $customersAddressesListener,
        private CustomersListener $customersListener,
        private CustomersGroupsListener $customersGroupsListener,
        private ProductsListener $productsListener,
        private ProductsManufacturersListener $productsManufacturersListener,
        private ProductsVariationsListener $productsVariationsListener,
        private ProductsCategoriesListener $productsCategoriesListener,
        private VouchersListener $vouchersListener,
        private NewsletterSubscribersListener $newsletterSubscribersListener,
        private OrderCouponListener $orderCouponListener,
        private OrderListener $orderListener,
        private OrderStatusListener $orderStatusListener,
        private ProductImagesListener $productImagesListener,
    ) {
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
        ];

        if (Utils::isCustomerFamilyActive()) {
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
     * Event listener for customer-related actions.
     */
    public function postCustomerInsert(CustomerEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->customersListener->postCustomerInsert($event);
        }
    }

    public function postCustomerUpdate(CustomerEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->customersListener->postCustomerUpdate($event);
        }
    }

    public function postCustomerDelete(CustomerEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->customersListener->postCustomerDelete($event);
        }
    }

    /**
     * Event listener for customer-family-related actions.
     */
    public function postCustomerGroupInsert(CustomerFamilyEvent $event): void
    {
        if (Utils::useRealTimeSynchronization() && Utils::isCustomerFamilyActive()) {
            $this->customersGroupsListener->postCustomerGroupInsert($event);
        }
    }

    public function postCustomerGroupUpdate(CustomerFamilyEvent $event): void
    {
        if (Utils::useRealTimeSynchronization() && Utils::isCustomerFamilyActive()) {
            $this->customersGroupsListener->postCustomerGroupUpdate($event);
        }
    }

    public function postCustomerGroupDelete(CustomerFamilyEvent $event): void
    {
        if (Utils::useRealTimeSynchronization() && Utils::isCustomerFamilyActive()) {
            $this->customersGroupsListener->postCustomerGroupDelete($event);
        }
    }

    /**
     * Event listener for customers-addresses-related actions.
     */
    public function postAddressInsert(AddressEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->customersAddressesListener->postAddressInsert($event);
        }
    }

    public function postAddressUpdate(AddressEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->customersAddressesListener->postAddressUpdate($event);
        }
    }

    public function postAddressDelete(AddressEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->customersAddressesListener->postAddressDelete($event);
        }
    }

    /**
     * Event listener for newsletter-related actions.
     */
    public function postNewsletterInsert(NewsletterEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->newsletterSubscribersListener->postNewsletterInsert($event);
        }
    }

    public function postNewsletterUpdate(NewsletterEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->newsletterSubscribersListener->postNewsletterUpdate($event);
        }
    }

    public function postNewsletterDelete(NewsletterEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->newsletterSubscribersListener->postNewsletterUpdate($event);
        }
    }

    /**
     * Event listener for order-coupon-related actions.
     */
    public function postOrderCouponInsert(OrderCouponEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->orderCouponListener->postOrderCouponInsert($event);
        }
    }

    /**
     * Event listener for order-related actions.
     */
    public function postOrderInsert(OrderEvent $event): void
    {
        $this->orderListener->postOrderInsert($event);
    }

    public function postOrderUpdate(OrderEvent $event): void
    {
        $this->orderListener->postOrderUpdate($event);
    }

    public function postOrderDelete(OrderEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->orderListener->postOrderDelete($event);
        }
    }

    /**
     * Event listener for order-status-related actions.
     */
    public function postOrderStatusInsert(OrderStatusEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->orderStatusListener->postOrderStatusInsert($event);
        }
    }

    public function postOrderStatusUpdate(OrderStatusEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->orderStatusListener->postOrderStatusUpdate($event);
        }
    }

    public function postOrderStatusDelete(OrderStatusEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->orderStatusListener->postOrderStatusDelete($event);
        }
    }

    /**
     * Event listener for product-images-related actions.
     */
    public function postProductImageInsert(ProductImageEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->productImagesListener->postProductImageInsert($event, $this->dispatcher);
        }
    }

    public function postProductImageUpdate(ProductImageEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->productImagesListener->postProductImageUpdate($event, $this->dispatcher);
        }
    }

    public function postProductImageDelete(ProductImageEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->productImagesListener->postProductImageDelete($event);
        }
    }

    /**
     * Event listener for product-categories-related actions.
     */
    public function postCategoryInsert(CategoryEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->productsCategoriesListener->postCategoryInsert($event);
        }
    }

    public function postCategoryUpdate(CategoryEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->productsCategoriesListener->postCategoryUpdate($event);
        }
    }

    public function postCategoryDelete(CategoryEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->productsCategoriesListener->postCategoryDelete($event);
        }
    }

    /**
     * Event listener for products-related actions.
     */
    public function postProductInsert(ProductCreateEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->productsListener->postProductInsert($event, $this->dispatcher);
        }
    }

    public function postProductUpdate(ProductUpdateEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->productsListener->postProductUpdate($event, $this->dispatcher);
        }
    }

    public function postProductDelete(ProductEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->productsListener->postProductDelete($event);
        }
    }

    /**
     * Event listener for products-manufacturers-related actions.
     */
    public function postBrandInsert(BrandEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->productsManufacturersListener->postBrandInsert($event);
        }
    }

    public function postBrandUpdate(BrandEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->productsManufacturersListener->postBrandUpdate($event);
        }
    }

    public function postBrandDelete(BrandEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->productsManufacturersListener->postBrandDelete($event);
        }
    }

    /**
     * Event listener for products-variations-related actions.
     */
    public function postProductSaleElementsInsert(ProductSaleElementCreateEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->productsVariationsListener->postProductSaleElementsInsert($event, $this->dispatcher);
        }
    }

    public function postProductSaleElementsUpdate(ProductSaleElementUpdateEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->productsVariationsListener->postProductSaleElementsUpdate($event, $this->dispatcher);
        }
    }

    public function postProductSaleElementsDelete(ProductSaleElementsEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->productsVariationsListener->postProductSaleElementsDelete($event);
        }
    }

    /**
     * Event listener for coupon-related actions.
     */
    public function postCouponInsert(CouponEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->vouchersListener->postCouponInsert($event);
        }
    }

    public function postCouponUpdate(CouponEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->vouchersListener->postCouponUpdate($event);
        }
    }

    public function postCouponDelete(CouponEvent $event): void
    {
        if (Utils::useRealTimeSynchronization()) {
            $this->vouchersListener->postCouponDelete($event);
        }
    }
}
