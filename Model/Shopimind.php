<?php


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

    public function setParentId($parentId)
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

    public function setFile($file)
    {
        $this->setFileRelatedByFileId($file);
    }

    public function getParentFileModel()
    {
        return $this->getParentFile();
    }

    public function getUpdateFormId()
    {
        // return 'carousel.image'; // Exemple, à adapter à votre besoin
    }

    public function getUploadDir()
    {
        // Implémentation de la méthode getUploadDir
        // return '/var/www/thelia/web/assets'; // Exemple, à adapter à votre besoin
    }

    public function getRedirectionUrl()
    {
        // return '/admin/module/my_module'; // Exemple, à adapter à votre besoin
    }

    public function getQueryInstance()
    {
        // return self::query();
    }
     public function setTitle($title)
    {
        $this->setYourTitlePropertyHere($title);
    }

    public function getTitle()
    {
        return $this->getYourTitlePropertyHere();
    }

    public function setChapo($chapo)
    {
        $this->setYourChapoPropertyHere($chapo);
    }

    public function setDescription($description)
    {
        $this->setYourDescriptionPropertyHere($description);
    }

    public function setPostscriptum($postscriptum)
    {
        $this->setYourPostscriptumPropertyHere($postscriptum);
    }

    public function setLocale($locale)
    {
        $this->setYourLocalePropertyHere($locale);
    }

    public function setVisible($visible)
    {
        $this->setYourVisiblePropertyHere($visible);
    }
    
}
