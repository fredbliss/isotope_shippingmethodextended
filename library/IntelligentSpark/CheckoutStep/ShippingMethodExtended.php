<?php

/**
 * Isotope eCommerce for Contao Open Source CMS
 *
 * Copyright (C) 2009-2016 terminal42 gmbh & Isotope eCommerce Workgroup
 *
 * @link       https://isotopeecommerce.org
 * @license    https://opensource.org/licenses/lgpl-3.0.html
 */

namespace IntelligentSpark\CheckoutStep;

use Isotope\Interfaces\IsotopeCheckoutStep;
use Isotope\Interfaces\IsotopeProductCollection;
use Isotope\Isotope;
use Isotope\Model\Shipping;
use Isotope\Module\Checkout;
use Isotope\CheckoutStep\ShippingMethod;
use Isotope\Template;

/**
 * ShippingMethod checkout step lets the user choose a shipping method.
 */
class ShippingMethodExtended extends ShippingMethod
{
    /**
     * Shipping modules.
     * @var array
     */
    private $modules;

    /**
     * Shipping options.
     * @var array
     */
    private $options;

    /**
     * Returns true if the current cart has shipping
     *
     * @inheritdoc
     */
    public function isAvailable()
    {
        return Isotope::getCart()->requiresShipping();
    }

    /**
     * Skip the checkout step if only one option is available
     *
     * @inheritdoc
     */
    public function isSkippable()
    {
        if (!$this->objModule->canSkipStep('shipping_method_extended')) {
            return false;
        }

        $this->initializeModules();

        return 1 === count($this->options);
    }

    /**
     * @inheritdoc
     */
    public function generate()
    {
        $this->initializeModules();

        if (empty($this->modules)) {
            $this->blnError = true;

            \System::log('No shipping methods available for cart ID ' . Isotope::getCart()->id, __METHOD__, TL_ERROR);

            /** @var Template|\stdClass $objTemplate */
            $objTemplate           = new Template('mod_message');
            $objTemplate->class    = 'shipping_method';
            $objTemplate->hl       = 'h2';
            $objTemplate->headline = $GLOBALS['TL_LANG']['MSC']['shipping_method'];
            $objTemplate->type     = 'error';
            $objTemplate->message  = $GLOBALS['TL_LANG']['MSC']['noShippingModules'];

            return $objTemplate->parse();
        }

        /** @var \Widget $objWidget */
        $objWidget = new $GLOBALS['TL_FFL']['radio'](
            [
                'id'          => $this->getStepClass(),
                'name'        => $this->getStepClass(),
                'mandatory'   => true,
                'options'     => $this->options,
                'value'       => Isotope::getCart()->shipping_id,
                'storeValues' => true,
                'tableless'   => true,
            ]
        );

        // If there is only one shipping method, mark it as selected by default
        if (count($this->modules) === 1) {
            $objModule        = reset($this->modules);
            $objWidget->value = $objModule->id;
            Isotope::getCart()->setShippingMethod($objModule);
        }

        if (\Input::post('FORM_SUBMIT') == $this->objModule->getFormId()) {
            $objWidget->validate();

            if (!$objWidget->hasErrors()) {
                Isotope::getCart()->setShippingMethod($this->modules[$objWidget->value]);
            }

            // !HOOK: shipping method submission block.  Allows us to include modifications to shipping selections
            // such as shipping upgrade surcharges selected by the customer.
            if (isset($GLOBALS['ISO_HOOKS']['shippingMethodSubmit']) && is_array($GLOBALS['ISO_HOOKS']['shippingMethodSubmit'])) {
                foreach ($GLOBALS['ISO_HOOKS']['shippingMethodSubmit'] as $callback) {
                    $objCallback = \System::importStatic($callback[0]);

                    $objCallback->{$callback[1]}($this);
                }
            }
        }

        if (!Isotope::getCart()->hasShipping() || !isset($this->modules[Isotope::getCart()->shipping_id])) {
            $this->blnError = true;
        }

        /** @var Template|\stdClass $objTemplate */
        $objTemplate                  = new Template('iso_checkout_shipping_method_extended');
        $objTemplate->headline        = $GLOBALS['TL_LANG']['MSC']['shipping_method'];
        $objTemplate->message         = $GLOBALS['TL_LANG']['MSC']['shipping_method_message'];
        $objTemplate->options         = $objWidget->parse();
        $objTemplate->upgrades        = $this->renderUpgradeOptions($objModule);
        $objTemplate->shippingMethods = $this->modules;

        return $objTemplate->parse($objModule);
    }

    protected function renderUpgradeOptions($objModule) {
        // !HOOK: shipping method label.  Allows us to append additional form controls for shipping upgrades.
        if (isset($GLOBALS['ISO_HOOKS']['renderUpgradeOptions']) && is_array($GLOBALS['ISO_HOOKS']['renderUpgradeOptions'])) {
            foreach ($GLOBALS['ISO_HOOKS']['renderUpgradeOptions'] as $callback) {
                $objCallback = \System::importStatic($callback[0]);

                return $objCallback->{$callback[1]}($this,$objModule);
            }
        }

        return '';
    }
    /**
     * Initialize modules and options
     */
    private function initializeModules()
    {
        if (null !== $this->modules && null !== $this->options) {
            return;
        }

        $this->modules = array();
        $this->options = array();

        $arrIds = deserialize($this->objModule->iso_shipping_modules);

        if (!empty($arrIds) && is_array($arrIds)) {
            $arrColumns = array('id IN (' . implode(',', $arrIds) . ')');

            if (true !== BE_USER_LOGGED_IN) {
                $arrColumns[] = "enabled='1'";
            }

            /** @var Shipping[] $objModules */
            $objModules = Shipping::findBy(
                $arrColumns, null, array('order' => \Database::getInstance()->findInSet('id', $arrIds))
            );

            if (null !== $objModules) {
                foreach ($objModules as $objModule) {

                    if (!$objModule->isAvailable()) {
                        continue;
                    }

                    $strLabel = $objModule->getLabel();
                    $fltPrice = $objModule->getPrice();

                    if ($fltPrice != 0) {
                        if ($objModule->isPercentage()) {
                            $strLabel .= ' (' . $objModule->getPercentageLabel() . ')';
                        }

                        $strLabel .= ': ' . Isotope::formatPriceWithCurrency($fltPrice);
                    }

                    if ($objModule->note != '') {
                        $strLabel .= '<span class="note">' . $objModule->note . '</span>';
                    }

                    $this->options[] = array(
                        'value' => $objModule->id,
                        'label' => $strLabel,
                    );

                    $this->modules[$objModule->id] = $objModule;
                }
            }
        }
    }


}