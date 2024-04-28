<?php

namespace Classes\Moisklad;

class ExportController
{
    const BASE_URL = 'https://api.moysklad.ru/api/remap/1.2';
    private $entity_base = '/entity';
    public $entity = '/customerorder';
    public $prefix = '/export';
    public $username = '';
    public $password = '';
    
    public array $agent;
    public array $organization;
    public array $store;
    public string $shipment_id;
    public array $shipments;
    public array $shipment_print_sheets;
    
    
    
    public function processRequest(string $method, ?string $id, ?string $c_id, ?string $cn_id)
    {
        if ($id !== null) {
            if ($method === "GET") {
                
                switch ($c_id) {
                    
                    case 'order' :
                        
                        $old = 'f0d6d7cd-e0f3-431c-9464-30b924ae0224';
                        $template_id = '650f86ff-6ca4-4c2c-b2eb-36c6f3bfcdf6';
                        $url = ExportController::BASE_URL . $this->entity_base . $this->entity . '/' . $id . $this->prefix;
                        $custom_template_href = 'https://api.moysklad.ru/api/remap/1.2/entity/customerorder/metadata/customtemplate/'.$template_id;
                        
                        $payload = [
                            "template" => [
                                "meta" => [
                                    "href" => $custom_template_href,
                                    "type" => "customtemplate",
                                    "mediaType" => "application/json"
                                ]
                            ],
                            "extension" => "xls"
                        ];
                        
                        return $this->curlConnection($url, 'POST', $payload, 'main');
                        break;
                    case 'bill' :
                        
                        $old = 'f0d6d7cd-e0f3-431c-9464-30b924ae0224';
                        $template_id = '40875718-82af-408f-963c-a79484d3d730';
                        $url = $id. $this->prefix;
                        $custom_template_href = 'https://api.moysklad.ru/api/remap/1.2/entity/invoiceout/metadata/customtemplate/'.$template_id;
                        
                        $payload = [
                            "template" => [
                                "meta" => [
                                    "href" => $custom_template_href,
                                    "type" => "customtemplate",
                                    "mediaType" => "application/json"
                                ]
                            ],
                            "extension" => "pdf"
                        ];
                        
                        return $this->curlConnection($url, 'POST', $payload, 'main');
                        break;
                    case 'ttn' :
                        
                        $old = 'f0d6d7cd-e0f3-431c-9464-30b924ae0224';
                        $template_id = '251763c0-adea-4518-8ae4-a20a0c4751e4';
                        $url = $id . $this->prefix;
                        $custom_template_href = 'https://api.moysklad.ru/api/remap/1.2/entity/demand/metadata/customtemplate/'.$template_id;
                        
                        $payload = [
                            "template" => [
                                "meta" => [
                                    "href" => $custom_template_href,
                                    "type" => "customtemplate",
                                    "mediaType" => "application/json"
                                ]
                            ],
                            "extension" => "pdf"
                        ];
                        
                        return $this->curlConnection($url, 'POST', $payload, 'main');
                        break;
                    case 'shipment':
                        
                        ### CREATE SHIPMENT FROM CUSTOMER ORDER ###
                        if ($cn_id === 'create') {
                            
                            # Запрос на получение id, контрагента заказа GET
                            
                            $url = ExportController::BASE_URL . $this->entity_base . $this->entity . '/' . $id ;
                            $this->getFormatResponse($url, 'GET','id_agent',  null);
                            
                            # Запрос на создание отгрузки получаем все данные organization, store PUT
                            
                            $customer_order = ExportController::BASE_URL . $this->entity_base . $this->entity . '/' . $id;
                            $customer_order = "https://api.moysklad.ru/api/remap/1.2/entity/customerorder/$id";
                            $payload = [
                                "customerOrder" => [
                                    "meta" => [
                                        "href" => $customer_order,
                                        "metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/customerorder/metadata",
                                        "type" => "customerorder",
                                        "mediaType" => "application/json"
                                    ]
                                ]
                            ];
                            
                            $this->store = [
                                "meta" => [
                                    "href" => "https://api.moysklad.ru/api/remap/1.2/entity/store/4cb56775-085a-11ed-0a80-03ea0014d735",
                                    "type" => "store",
                                    "mediaType" => "application/json",
                                ]
                            ];
                            
                            $this->entity = '/demand';
                            $this->prefix = '/new';
                            
                            $url = ExportController::BASE_URL . $this->entity_base . $this->entity .  $this->prefix;
                            $this->getFormatResponse($url, 'PUT', 'create_shipment', $payload);
                            
                            # Берем данные id отгузки, данные organization, store и отправляем  POST
                            $payload = [
                                "organization" => $this->organization,
                                "agent" => $this->agent,
                                "store" => $this->store
                            ];
                            
                            
                            $url = ExportController::BASE_URL . $this->entity_base . $this->entity;
                            $this->getFormatResponse($url, 'POST', 'get_shipment_id', $payload);
                            # Печатаем отгрузку
                            $custom_template_shipment_href = "https://api.moysklad.ru/api/remap/1.2/entity/demand/metadata/embeddedtemplate/4fdd996f-d2fd-4500-baf2-fea33b6db078";
                            $payload = [
                                "template" => [
                                    "meta" => [
                                        "href" => $custom_template_shipment_href,
                                        "type" => "embeddedtemplate",
                                        "mediaType" => "application/json"
                                    ]
                                ],
                                "extension" => "xls"
                            ];
                            
                            $this->prefix = '/export';
                            $url = ExportController::BASE_URL . $this->entity_base . $this->entity . '/' . $this->shipment_id . $this->prefix;
                            echo json_encode($this->curlConnection($url, 'POST', $payload, 'main'));
                            
                        }
                        ### LIST SHIPMENT FROM CUSTOMER ORDER ###
                        else {
                            # Получаем список всех отгрузок
                            $url = ExportController::BASE_URL . $this->entity_base . $this->entity . '/' . $id ;
                            $this->getFormatResponse($url, 'GET','get_list_shipment_from_order',  null);
                            # Получаем файлы для печати для всех отгрузок
                            
                            $custom_template_shipment_href = "https://api.moysklad.ru/api/remap/1.2/entity/demand/metadata/embeddedtemplate/4fdd996f-d2fd-4500-baf2-fea33b6db078";
                            $payload = [
                                "template" => [
                                    "meta" => [
                                        "href" => $custom_template_shipment_href,
                                        "type" => "embeddedtemplate",
                                        "mediaType" => "application/json"
                                    ]
                                ],
                                "extension" => "xls"
                            ];
                            
                            foreach ($this->shipments as $k => $shipment) {
                                $url = $shipment['href'] . $this->prefix;
                                
                                $this->shipment_print_sheets[$k]['xls_href'] =  $this->curlConnection($url, 'POST', $payload, 'main');
                                
                            }
                            echo json_encode($this->shipment_print_sheets);
                        }
                    
                    
                }
                
                
            } else {
                $this->respondMethodNotAllowed("GET");
            }
            
        }
    }
    
    private function curlConnection(string $url, string $method, ?array $payload, ?string $return_mod)
    {
        $curl = curl_init();
        
        $headers = [
            'Content-Type: application/json',
            "cache-control: no-cache",
            "Accept-Encoding: gzip"
        ];
        
        if ($payload) {
            
            $payload = json_encode($payload);
        }
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_USERPWD => $this->username . ":" . $this->password,
            CURLOPT_ENCODING => 'gzip'
        ));
        
        $response = curl_exec($curl);
        $headers = curl_getinfo($curl);
        
        if ($headers) {
            curl_close($curl);
            if ($return_mod === 'main') {
                return $headers['redirect_url'];
            } else {
                return json_decode($response, true);
            }
            
        } else {
            curl_error($curl);
        }
    }
    
    public function getFormatResponse(string $url, string $method, ?string $mod, ?array $payload)
    {
        
        $r = $this->curlConnection($url, $method, $payload, null);
        
        switch ($mod) {
            case 'id_agent':
                $this->agent = $r['agent'];
                break;
            
            case 'create_shipment':
                $this->organization = $r['organization'];
                break;
            
            case 'get_shipment_id':
                $this->shipment_id = $r['id'];
                break;
            
            case 'get_list_shipment_from_order':
                foreach ($r['demands'] as $k => $v) {
                    $this->shipments[$k]['href'] = $v['meta']['href'];
                }
                break;
            
        }
    }
    
    
    private function respondMethodNotAllowed(string $allowed_methods): void
    {
        http_response_code(405);
        header("Allow: $allowed_methods");
    }
    
    public function getOrder($id)
    {
        $url = 'https://api.moysklad.ru/api/remap/1.2/entity/customerorder?filter=externalCode='.$id;
        $result = $this->curlConnection($url, 'GET', [], false);
        if (isset($result['rows']) and $result['rows'][0])
            return $result['rows'][0];
        return false;
    }
    
}