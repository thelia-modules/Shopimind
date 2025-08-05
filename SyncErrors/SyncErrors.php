<?php

namespace Shopimind\SyncErrors;

use Shopimind\Model\ShopimindSyncErrorsQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Shopimind\lib\Utils;
use Shopimind\Model\ShopimindQuery;

class SyncErrors
{
    /**
     * Download multiple sync errors as CSV
     * 
     * @param Request $request
     * @return Response
     */
    public function downloadMultipleSyncErrorsCsv(Request $request)
    {
        return new JsonResponse([
            'status' => false,
            'message' => 'This feature is disabled for the moment'
        ], 400);
        
        try {    
            $config = ShopimindQuery::create()->findOne();
            $apiKey = $request->headers->get('api-spm-key');
            if ( !( $apiKey === sha1($config->getApiPassword()) ) ) {
                return new JsonResponse([
                    'success' =>  false,
                    'message' => 'Unauthorized.',
                ], 401);
            }

            $idShopAskSync = $request->query->getInt('id-shop-ask-syncs');
            
            if (empty($idShopAskSync)) {
                throw new \Exception("The id-shop-ask-syncs parameter is required");
            }

            $zip = new \ZipArchive();   
            $zipFilename = tempnam(sys_get_temp_dir(), 'errors_') . '.zip';
            
            if ($zip->open($zipFilename, \ZipArchive::CREATE) !== true) {
                throw new \Exception("Unable to create the ZIP file");
            }

            $objectTypes = ShopimindSyncErrorsQuery::create()
                ->filterByIdShopAskSyncs($idShopAskSync)
                ->groupBy('object_type')
                ->find();

            if ($objectTypes->count() === 0) {
                throw new \Exception("No errors found for this synchronization");
            }

            $processedTypes = [];
            
            foreach ($objectTypes as $error) {
                $objectType = $error->getObjectType();
                
                if (!in_array($objectType, $processedTypes)) {
                    $csvContent = $this->generateCsvForObjectType($idShopAskSync, $objectType);
                    $zip->addFromString("errors_{$objectType}.csv", $csvContent);
                    $processedTypes[] = $objectType;
                }
            }

            $zip->close();

            $response = new BinaryFileResponse($zipFilename);
            $response->headers->set('Content-Type', 'application/zip');
            $response->headers->set('Content-Disposition', 'attachment; filename="sync_errors_'.$idShopAskSync.'_'.date('Y-m-d').'.zip"');
            $response->deleteFileAfterSend(true);

            return $response;

        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Generate CSV for a specific object type
     * 
     * @param int $idShopAskSync
     * @param string $objectType
     * @return string
     */
    public function generateCsvForObjectType($idShopAskSync, $objectType)
    {
        try {
            if (empty($idShopAskSync) || empty($objectType)) {
                throw new \Exception("Both id_shop_ask_syncs and object_type parameters are required");
            }

            $errors = ShopimindSyncErrorsQuery::create()
                ->filterByIdShopAskSyncs($idShopAskSync)
                ->filterByObjectType($objectType)
                ->find();

            if ($errors->count() === 0) {
                throw new \Exception("No synchronization errors found for these criteria");
            }

            $output = fopen('php://temp', 'w+');
            
            $headersWritten = false;
            $headers = [];

            foreach ($errors as $error) {
                $errorData = $error->getData();
                $errorMessages = $error->getErrorMessage();

                if (!$headersWritten && !empty($errorData)) {
                    $headers = array_keys($errorData[0]);
                    $headers[] = 'id_sync_error';
                    $headers[] = 'object_type';
                    $headers[] = 'id_shop_ask_syncs';
                    $headers[] = 'error_message';
                    $headers[] = 'date_error';
                    fputcsv($output, $headers, ';');
                    $headersWritten = true;
                }

                $errorMap = [];
                if (isset($errorMessages['invalidFields'])) {
                    foreach ($errorMessages['invalidFields'] as $invalidField) {
                        $errorMap[$invalidField['objectIndex']] = $invalidField['validationErrors'][0]['message'];
                    }
                }

                foreach ($errorData as $index => $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    
                    $csvRow = [];
                    
                    foreach ($row as $key => $value) {
                        if (is_array($value)) {
                            $csvRow[$key] = json_encode($value, JSON_UNESCAPED_UNICODE) .' ';
                        } elseif ($value === null) {
                            $csvRow[$key] = 'null';
                        } elseif ($value === false) {
                            $csvRow[$key] = 'false';
                        } elseif ($value === true) {
                            $csvRow[$key] = 'true';
                        } else {
                            $csvRow[$key] = $value;
                        }
                    }
                    
                    $csvRow['id_sync_error'] = $error->getId();
                    $csvRow['object_type'] = $error->getObjectType();
                    $csvRow['id_shop_ask_syncs'] = $error->getIdShopAskSyncs();
                    $csvRow['error_message'] = $errorMap[$index];
                    $csvRow['date_error'] = $error->getTimestamp()->format('Y-m-d H:i:s');

                    fputcsv($output, $csvRow, ';');
                }
            }

            rewind($output);
            $content = stream_get_contents($output);
            fclose($output);
            
            return $content;
        } catch (\Throwable $th) {
            return 'An error occurred while generating the CSV file';
        }
    }
}