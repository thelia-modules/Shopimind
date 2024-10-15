<?php

namespace Shopimind\PassiveSynchronization;

require_once realpath(__DIR__.'/../').'/vendor-module/autoload.php';

use Thelia\Model\NewsletterQuery;
use Symfony\Component\HttpFoundation\Request;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmNewsletterSubscribers;
use Shopimind\Data\NewsletterSubscribersData;

class SyncNewsletterSubscribers
{
    /**
     * Process synchronization for newsletter subscribers
     *
     * @param $lastUpdate
     * @param $ids
     * @return array
     */
    public static function processSyncNewsletterSubscribers( $lastUpdate, $ids, $requestedBy ): array
    {
        $newsletterSubscriberIds = null;
        if ( !empty( $ids ) ) {
            $newsletterSubscriberIds = ( !is_array( $ids ) && $ids > 0 ) ? array( $ids ) : $ids;
        }

        if ( empty( $lastUpdate ) ) {
            if ( empty( $newsletterSubscriberIds ) ) {
                $count = NewsletterQuery::create()->find()->count();
            }else {
                $count = NewsletterQuery::create()->filterById( $newsletterSubscriberIds )->find()->count();
            }
        } else {
            if ( empty( $newsletterSubscriberIds ) ) {
                $count = NewsletterQuery::create()->filterByUpdatedAt( $lastUpdate, '>=')->count();
            }else {
                $count = NewsletterQuery::create()->filterById( $newsletterSubscriberIds )->filterByUpdatedAt( $lastUpdate, '>=')->count();
            }
        }

        if ( $count == 0 ) {
            return [
                'success' => true,
                'count' => 0,
            ];
        }

        $synchronizationStatus = Utils::loadSynchronizationStatus();

        if (
            $synchronizationStatus
            && isset( $synchronizationStatus['synchronization_status']['newsletter_subscribers'] )
            && $synchronizationStatus['synchronization_status']['newsletter_subscribers'] == 1
            ) {
            return [
                'success' => false,
                'message' => 'A previous process is still running.',
            ];
        }

        Utils::updateSynchronizationStatus( 'newsletter_subscribers', 1 );

        Utils::launchSynchronisation( 'newsletter-subscribers', $lastUpdate, $newsletterSubscriberIds, $requestedBy );

        return [
            'success' => true,
            'count' => $count,
        ];
    }

    /**
     * Synchronizes newsletter subscribers.
     *
     * @return void
     */
    public static function syncNewsletterSubscribers( Request $request )
    {
        try {
            $body =  json_decode( $request->getContent(), true );

            $lastUpdate = ( isset( $body['last_update'] ) ) ? $body['last_update'] : null;

            $newsletterSubscriberIds = null;
            $ids = ( isset( $body['ids'] ) ) ? $body['ids'] : null;
            if ( !empty( $ids ) ) {
                $newsletterSubscriberIds = ( !is_array( $ids ) && $ids > 0 ) ? array( $ids ) : $ids;
            }

            $requestedBy = ( isset( $body['requestedBy'] ) ) ? $body['requestedBy'] : null;

            $offset = 0;
            $limit = 1;

            $hasMore = true;

            do {
                if ( empty( $lastUpdate ) ) {
                    if ( empty( $newsletterSubscriberIds ) ) {
                        $newsletters = NewsletterQuery::create()->offset( $offset )->limit( $limit )->find();
                    }else {
                        $newsletters = NewsletterQuery::create()->filterById( $newsletterSubscriberIds )->offset( $offset )->limit( $limit )->find();
                    }
                } else {
                    $lastUpdate = trim( $lastUpdate, '"\'');
                    if ( empty( $newsletterSubscriberIds ) ) {
                        $newsletters = NewsletterQuery::create()->offset( $offset )->limit( $limit )->filterByUpdatedAt( $lastUpdate, '>=' );
                    }else {
                        $newsletters = NewsletterQuery::create()->offset( $offset )->filterById( $newsletterSubscriberIds )->limit( $limit )->filterByUpdatedAt( $lastUpdate, '>=' );
                    }
                }

                if ( $newsletters->count() < $limit ) {
                    $hasMore = false;
                }else {
                    $offset += $limit;
                }

                if ( $newsletters->count() > 0 ) {
                    $data = [];
                    foreach ( $newsletters as $newsletter ) {
                        $data[] = NewsletterSubscribersData::formatNewsletterSubscriber( $newsletter );
                    }

                    $requestHeaders = $requestedBy ? [ 'answered-for' => $requestedBy ] : [];
                    $response = SpmNewsletterSubscribers::bulkSave( Utils::getAuth( $requestHeaders ), $data );

                    Utils::handleResponse( $response );

                    Utils::log( 'newsletterSubscribers' , 'passive synchronization', json_encode( $response ) );
                }

            } while ( $hasMore );

        } catch (\Throwable $th) {
            Utils::log( 'newsletterSubscribers' , 'passive synchronization', $th->getMessage() );
        }  finally {
            Utils::log( 'newsletterSubscribers', 'passive synchronization', 'finally', null);
            Utils::updateSynchronizationStatus( 'newsletter_subscribers', 0 );
        }

        Utils::updateSynchronizationStatus( 'newsletter_subscribers', 0 );
    }
}
