<?php
namespace MiniOrange\SecuritySuite\ViewModel\Adminhtml;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\Data\Form\FormKey;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Escaper;

class Configure implements ArgumentInterface
{
    protected $formKey;
    protected $assetRepo;
    protected $urlBuilder;
    protected $request;
    protected $escaper;

    public function __construct(
        Repository $assetRepo,
        FormKey $formKey,
        UrlInterface $urlBuilder,
        RequestInterface $request,
        Escaper $escaper
    ) {
        $this->assetRepo = $assetRepo;
        $this->formKey = $formKey;
        $this->urlBuilder = $urlBuilder;
        $this->request = $request;
        $this->escaper = $escaper;
    }

    /**
     * Get the view file URL
     */
    public function getViewFileUrl($fileId)
    {
        return $this->assetRepo->getUrl($fileId);
    }
    
    /**
     * Get form key value (not HTML)
     */
    public function getBlockHtml()
    {
        return $this->formKey->getFormKey();
    }

    /**
     * Generate admin URL
     */
    public function getUrl($route, $params = [])
    {
        return $this->urlBuilder->getUrl($route, $params);
    }

    /**
     * Get request parameters
     */
    public function getRequest()
    {
        return $this->request->getParams();
    }

    /**
     * Escape the URL
     */
    public function escapeUrl($url)
    {
        return $this->escaper->escapeUrl($url);
    }
    
}