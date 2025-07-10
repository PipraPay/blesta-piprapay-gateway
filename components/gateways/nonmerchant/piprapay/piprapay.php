<?php

/**
 * PipraPay Gateway
 *
 * Allows users to pay via BD Payment Methods
 *
 */

class Piprapay extends NonmerchantGateway
{
    private $meta;

    public function __construct()
    {

        $this->loadConfig(dirname(__FILE__) . DS . 'config.json');

        Loader::loadComponents($this, ['Input']);

        Language::loadLang('piprapay', null, dirname(__FILE__) . DS . 'language' . DS);
    }

    public function setMeta(array $meta = null)
    {
        $this->meta = $meta;
    }

    public function getSettings(array $meta = null)
    {
        $this->view = new View('settings', 'default');
        $this->view->setDefaultView(
            'components' . DS . 'gateways' . DS . 'nonmerchant' . DS . 'piprapay' . DS
        );

        Loader::loadHelpers($this, ['Form', 'Html']);

        $this->view->set('meta', $meta);

        return $this->view->fetch();
    }

    public function editSettings(array $meta)
    {
        $rules = [
            'api_key'       => [
                'valid' => [
                    'rule'    => 'isEmpty',
                    'negate'  => true,
                    'message' => Language::_('PipraPay.!error.api_key.valid', true),
                ],
            ],
            'api_url'       => [
                'valid' => [
                    'rule'    => 'isEmpty',
                    'negate'  => true,
                    'message' => Language::_('PipraPay.!error.api_url.valid', true),
                ],
            ],
            'pp_currency_code' => [
                'valid' => [
                    'rule'    => 'isEmpty',
                    'negate'  => true,
                    'message' => Language::_('PipraPay.!error.pp_currency_code.valid', true),
                ],
            ],
        ];
        $this->Input->setRules($rules);

        $this->Input->validates($meta);

        return $meta;
    }

    public function encryptableFields()
    {
        return ['api_key', 'api_url'];
    }

    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

    public function buildProcess(array $contact_info, $amount, array $invoice_amounts = null, array $options = null)
    {
        Loader::loadModels($this, ['Companies']);

        $formatAmount = round($amount, 2);
        if (isset($options['recur']['amount'])) {
            $options['recur']['amount'] = round($options['recur']['amount'], 2);
        }

        $pp_currency_code = $this->meta['pp_currency_code'];
        
        $currency = ($this->currency ?? null);
        
        if (isset($invoice_amounts) && is_array($invoice_amounts)) {
            $invoices = $this->serializeInvoices($invoice_amounts);
        }

        $notification_url = Configure::get('Blesta.gw_callback_url')
        . Configure::get('Blesta.company_id') . '/piprapay/?client_id='
            . (isset($contact_info['client_id']) ? $contact_info['client_id'] : null);


        $url = $this->meta['api_url'].'/create-charge';
        
        $data = [
            "full_name" => ($contact_info['first_name'] ?? '') . ' ' . ($contact_info['last_name'] ?? ''),
            "email_mobile" => $this->emailFromClientId($contact_info['client_id']),
            "amount" => $formatAmount,
            "metadata" => [
                'customer_id' => ($contact_info['client_id'] ?? null),
                'invoices'    => $invoices,
                'currency'    => $currency,
                'amount'      => $amount,
            ],
            "redirect_url" => $notification_url,
            "return_type" => "GET",
            "cancel_url" => ($options['return_url'] ?? null),
            "webhook_url" => $notification_url,
            "currency" => $this->meta['pp_currency_code']
        ];
        
        $headers = [
            'accept: application/json',
            'content-type: application/json',
            'mh-piprapay-api-key: '.$this->meta['api_key']
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);

        curl_close($ch);

        $response_decode = json_decode($response, true);
        
        if(isset($response_decode['pp_url'])){
            header('Location:' . $response_decode['pp_url']);
            exit();
        }else{
            die("Initialization Error: " . $e->getMessage());
        }
    }

    private function emailFromClientId($id)
    {
        if (!isset($this->Record)) {
            Loader::loadComponents($this, ['Record']);
        }

        $contact = $this->Record->select(['contacts.email'])
            ->from('contacts')
            ->where('contacts.contact_type', '=', 'primary')
            ->where('contacts.client_id', '=', $id)
            ->fetch();
        if ($contact) {
            return $contact->email;
        }
        return null;
    }


    public function validate(array $get, array $post)
    {
        $pp_id = $get['pp_id'] ?? '';

        if (empty($pp_id)) {
              $rawData = file_get_contents("php://input");
              $data = json_decode($rawData, true);
            
              $headers = getallheaders();
            
              $received_api_key = '';
            
              if (isset($headers['mh-piprapay-api-key'])) {
                  $received_api_key = $headers['mh-piprapay-api-key'];
              } elseif (isset($headers['Mh-Piprapay-Api-Key'])) {
                  $received_api_key = $headers['Mh-Piprapay-Api-Key'];
              } elseif (isset($_SERVER['HTTP_MH_PIPRAPAY_API_KEY'])) {
                  $received_api_key = $_SERVER['HTTP_MH_PIPRAPAY_API_KEY']; // fallback if needed
              }
            
              if ($received_api_key !== $this->meta['api_key']) {
                  return;
              }
            
              $pp_id = $data['pp_id'] ?? '';
        }

        $status = 'pending';
        $success = false;

        if (!empty($pp_id)) {
            try {
                $url = $this->meta['api_url'].'/verify-payments';
                $payload = json_encode(['pp_id' => $pp_id]);
                
                $ch = curl_init($url);
                
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => [
                        'Accept: application/json',
                        'Content-Type: application/json',
                        'mh-piprapay-api-key: ' . $this->meta['api_key'],
                    ],
                    CURLOPT_POSTFIELDS => $payload,
                ]);
                
                $response = curl_exec($ch);
                curl_close($ch);
                                
                $response = json_decode($response, true);
                
            } catch (\Exception $e) {
                return;
            }

            if ($response['status'] === 'completed') {
                $status = 'approved';
                $success = true;
            }
        }

        if (!$success) {
            return;
        }

        return [
            'client_id'             => ($response['metadata']['customer_id'] ?? null),
            'amount'                => $response['metadata']['amount'],
            'currency'              => $response['metadata']['currency'],
            'invoices'              => $this->unserializeInvoices($response['metadata']['invoices'] ?? null),
            'status'                => $status,
            'reference_id'          => null,
            'transaction_id'        => $response['transaction_id'],
            'parent_transaction_id' => null,
        ];
    }

    public function success(array $get, array $post)
    {
        $pp_id = $get['pp_id'] ?? '';

        if (empty($pp_id)) {
              $rawData = file_get_contents("php://input");
              $data = json_decode($rawData, true);
            
              $headers = getallheaders();
            
              $received_api_key = '';
            
              if (isset($headers['mh-piprapay-api-key'])) {
                  $received_api_key = $headers['mh-piprapay-api-key'];
              } elseif (isset($headers['Mh-Piprapay-Api-Key'])) {
                  $received_api_key = $headers['Mh-Piprapay-Api-Key'];
              } elseif (isset($_SERVER['HTTP_MH_PIPRAPAY_API_KEY'])) {
                  $received_api_key = $_SERVER['HTTP_MH_PIPRAPAY_API_KEY']; // fallback if needed
              }
            
              if ($received_api_key !== $this->meta['api_key']) {
                  return;
              }
            
              $pp_id = $data['pp_id'] ?? '';
        }

        $status = 'pending';
        $success = false;

        if (!empty($pp_id)) {
            try {
                $url = $this->meta['api_url'].'/verify-payments';
                $payload = json_encode(['pp_id' => $pp_id]);
                
                $ch = curl_init($url);
                
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => [
                        'Accept: application/json',
                        'Content-Type: application/json',
                        'mh-piprapay-api-key: ' . $this->meta['api_key'],
                    ],
                    CURLOPT_POSTFIELDS => $payload,
                ]);
                
                $response = curl_exec($ch);
                curl_close($ch);
                                
                $response = json_decode($response, true);
                
            } catch (\Exception $e) {
                return;
            }

            if ($response['status'] === 'completed') {
                $status = 'approved';
                $success = true;
            }
        }

        if (!$success) {
            return;
        }

        return [
            'client_id'             => ($response['metadata']['customer_id'] ?? null),
            'amount'                => $response['metadata']['amount'],
            'currency'              => $response['metadata']['currency'],
            'invoices'              => $this->unserializeInvoices($response['metadata']['invoices'] ?? null),
            'status'                => $status,
            'reference_id'          => null,
            'transaction_id'        => $response['transaction_id'],
            'parent_transaction_id' => null,
        ];
    }


    public function refund($reference_id, $transaction_id, $amount, $notes = null)
    {
        $this->Input->setErrors($this->getCommonError('unsupported'));
    }

    public function void($reference_id, $transaction_id, $notes = null)
    {
        $this->Input->setErrors($this->getCommonError('unsupported'));
    }

    private function serializeInvoices(array $invoices)
    {
        $str = '';
        foreach ($invoices as $i => $invoice) {
            $str .= ($i > 0 ? '|' : '') . $invoice['id'] . '=' . $invoice['amount'];
        }

        return base64_encode($str);
    }

    private function unserializeInvoices($str)
    {
        if (empty($str)) {
            return null;
        }

        $str = base64_decode($str);

        $invoices = [];
        $temp = explode('|', $str);
        foreach ($temp as $pair) {
            $pairs = explode('=', $pair, 2);
            if (count($pairs) != 2) {
                continue;
            }
            $invoices[] = ['id' => $pairs[0], 'amount' => $pairs[1]];
        }

        return $invoices;
    }
}
