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

namespace Shopimind\Model;

use Shopimind\Model\Base\Shopimind as BaseShopimind;
use Thelia\Files\FileModelInterface;

class Shopimind extends BaseShopimind implements FileModelInterface
{
    public $apiId;
    public $apiPassword;
    public $nominativeReductions;
    public $cumulativeVouchers;
    public $outOfStockProductDisabling;
    public $scriptTag;

    public function setParentId($parentId): void
    {
        $this->setParent($parentId);
    }

    public function getParentId()
    {
        return $this->getParent();
    }

    public function getFile()
    {
        return $this->getFileRelatedByFileId();
    }

    public function setFile($file): void
    {
        $this->setFileRelatedByFileId($file);
    }

    public function getParentFileModel()
    {
        return $this->getParentFile();
    }

    public function getUpdateFormId(): void
    {
        // return 'carousel.image'; // Exemple, à adapter à votre besoin
    }

    public function getUploadDir(): void
    {
        // Implémentation de la méthode getUploadDir
        // return '/var/www/thelia/web/assets'; // Exemple, à adapter à votre besoin
    }

    public function getRedirectionUrl(): void
    {
        // return '/admin/module/my_module'; // Exemple, à adapter à votre besoin
    }

    public function getQueryInstance(): void
    {
        // return self::query();
    }

    public function setTitle($title): void
    {
        $this->setYourTitlePropertyHere($title);
    }

    public function getTitle()
    {
        return $this->getYourTitlePropertyHere();
    }

    public function setChapo($chapo): void
    {
        $this->setYourChapoPropertyHere($chapo);
    }

    public function setDescription($description): void
    {
        $this->setYourDescriptionPropertyHere($description);
    }

    public function setPostscriptum($postscriptum): void
    {
        $this->setYourPostscriptumPropertyHere($postscriptum);
    }

    public function setLocale($locale): void
    {
        $this->setYourLocalePropertyHere($locale);
    }

    public function setVisible($visible): void
    {
        $this->setYourVisiblePropertyHere($visible);
    }
}
