<?php 
/**
 * @category    Francosabolo
 * @package     Francosabolo_RegisterGuest
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Francosabolo_RegisterGuest_Model_Observer
{
    public function registerGuestUser($observer){
        $order = $observer->getEvent()->getOrder(); 

        $autoregister_array["customerSaved"] = false;
        $email        = $order->getCustomerEmail();
        $firstname    = $order->getCustomerFirstname();
        $lastname     = $order->getCustomerLastname();
        $website_id   = Mage::app()->getStore()->getWebsiteId();

        $customer->setWebsiteId($website_id)->loadByEmail($email);

        if (!$customer->getId()) {
            $billingAddress = $order->getBillingAddress();
            $street         = $billingAddress->getStreet();
            

            $params   = Mage::app()->getRequest()->getParams();
            $password = $params['billing']['customer_password'];
            
            if ($password == '') {$password = $customer->generatePassword(12);}
            

            $customer->setId(null)
                ->setSkipConfirmationIfEmail($email)
                ->setFirstname($firstname)
                ->setLastname($lastname)
                ->setEmail($email)
                ->setPassword($password)
                ->setPasswordConfirmation($password);

            $errors = array();
            $validationCustomer = $customer->validate();


            $_custom_address = array(
                'firstname' => $firstname,
                'lastname' => $lastname,
                'street' => array(
                    '0' => $street[0]
                ),
                'city' => $billingAddress->getCity(),
                'region_id' => $billingAddress->getRegionId(),
                'region' => $billingAddress->getRegion(),
                'postcode' => $billingAddress->getPostcode(),
                'country_id' => $billingAddress->getCountryId(),
                'telephone' => $billingAddress->getTelephone(),
                'fax' => $billingAddress->getFax()
            );

            if (is_array($validationCustomer)) {
                $errors = array_merge($validationCustomer, $errors);
            }

            $validationResult = count($errors) == 0;

            if (true === $validationResult) {
                try {
                    $customer->save();
                    $autoregister_array["customerSaved"] = true;
                    

                    if ($customer->getId()) {
                        $this->_setCustomerNewAddress($customer->getId(),$_custom_address);
                        $this->_associateOrderWithCustomer($email,$order);
                        Mage::dispatchEvent('customer_register_success', array('customer' => $customer));
                        $customer->sendNewAccountEmail();
                    }
                    
                } catch (Exception $e) {
                    Mage::log($e);
                }

            }
        } 
    }

    protected function _associateOrderWithCustomer($email, $order){
        $customer = Mage::getModel('customer/customer');
        $customer->setWebsiteId(Mage::app()->getStore()->getWebsiteId())->loadByEmail($email);

        if ($customer->getId()) {
            $order_increment_id = $order->getIncrementId() ;
            if($order->getCustomerIsGuest() == "1"){
                $order->setCustomerIsGuest(0);
            }
            if($order->getCustomerGroupId() == "0"){
                $order->setCustomerGroupId(1);
            }
            $order->setCustomer($customer);
            $order->save();
        }
    }

    protected function _setCustomerNewAddress($customerId, $address){
        $customAddress = Mage::getModel('customer/address');
        $customAddress->setData($address)
                ->setCustomerId($customerId)
                ->setIsDefaultBilling('1')
                ->setIsDefaultShipping('1')
                ->setSaveInAddressBook('1');

        $customAddress->save();
    }
}