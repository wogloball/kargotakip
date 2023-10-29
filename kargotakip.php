<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use DemeterChain\B;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use SoapClient;
use SoapHeader;


class BmysController extends Controller
{
    public function kargoTakip()
    {
        //exit();
        echo '<center style="font-size:20; font-weight:bold;">Kargo Takip Cron<br />';
        echo strtotime("2023-01-01 00:00:00");
        $gecenAy = \Carbon\Carbon::now()->subWeek(2)->getTimestamp();
        /*$kargoOrders = db::select('select *,o.id as id,o.barcode as barcode,o.name as name,c.name as cargo,c.id as c_id,sc.id as sc_id,sc.name as subcargo,ost.title as ost,o.time as time,c.type as type,c.query as query,o.order_status as orderstatus from orders as o
                                                 join cargos as c on o.cargo = c.id
                                                 left join cargos as sc on o.subcargo = sc.id
                                                 join order_statuses as ost on o.order_status = ost.id
                                                 where o.user_agent != "data_30_11" and o.cargo not in (25,238) and o.order_status in (3,9,21,45) and o.status = 1 and o.time >= \'' . $gecenAy . '\' order by o.lastupdate asc limit 20,20');*/

$kargoOrders = db::select('select *,o.id as id,o.barcode as barcode,o.name as name,c.name as cargo,c.id as c_id,sc.id as sc_id,sc.name as subcargo,ost.title as ost,o.time as time,c.type as type,c.query as query,o.order_status as orderstatus from orders as o
                                   join cargos as c on o.cargo = c.id
                                   left join cargos as sc on o.subcargo = sc.id
                                   join order_statuses as ost on o.order_status = ost.id
                                   where o.order_status in (3,9,21,45) and o.status = 1 and o.time >= \'' . $gecenAy . '\' order by o.lastupdate asc limit 20,20');

///      $kargoOrders = db::select('select *,o.id as id,o.barcode as barcode,o.name as name,c.name as cargo,c.id as c_id,sc.id as sc_id,sc.name as subcargo,ost.title as ost,o.time as time,c.type as type,c.query as query,o.order_status as orderstatus from orders as o
///                                               join cargos as c on o.cargo = c.id
///                                               left join cargos as sc on o.subcargo = sc.id
///                                               join order_statuses as ost on o.order_status = ost.id
///                                               where o.user_agent != "data_30_11" and o.cargo not in (25,238) and o.order_status in (9) and o.status = 1 and o.time >= \'' . $gecenAy . '\' order by o.id desc limit 0,20');

        echo '<table border="1">';
        foreach ($kargoOrders as $kargo):
            switch ($kargo->type) {
                case 1:
                    $kargoTipi = '1';
                    break;
                default:
                    $kargoTipi = $kargo->type;
                    break;
            }
            if ($kargo->type == 1) {
                $kargoQuery = json_decode($kargo->query);
                try {
                    $trackInfo = \App\Cargos::ajannet_getorderCargo($kargo->id);
                    $jsonTrack = json_decode($trackInfo['details']);

                    // kargo takip kontrol
                    $takipKontrol = DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->count();
                    if (!$takipKontrol) {
                        $gonderiDetay = array(
                            'gonderino' => $jsonTrack->gonderino,
                            'musteribarkod' => $jsonTrack->musteribarkod,
                            'cikisno' => $jsonTrack->cikisno,
                            'durum' => $jsonTrack->durum,
                            'subekod' => $jsonTrack->sube,
                            'sube' => $jsonTrack->sube,
                            'tutar' => '',
                            'url' => $jsonTrack->url
                        );
                        $track = array(
                            'order_id' => $kargo->id,
                            'cargo_type' => 1,
                            'details' => json_encode($gonderiDetay, JSON_UNESCAPED_UNICODE),
                            'lastupdate' => time()
                        );
                        DB::connection('laravel_destek')->table('cargos_track_numbers')->insertGetId($track);
                    } else {

                        $gonderiDetay = array(
                            'gonderino' => $jsonTrack->gonderino,
                            'musteribarkod' => $jsonTrack->musteribarkod,
                            'cikisno' => $jsonTrack->cikisno,
                            'durum' => $jsonTrack->durum,
                            'subekod' => $jsonTrack->sube,
                            'sube' => $jsonTrack->sube,
                            'tutar' => '',
                            'url' => $jsonTrack->url
                        );
                        $track = array(
                            'order_id' => $kargo->id,
                            'cargo_type' => 1,
                            'details' => json_encode($gonderiDetay, JSON_UNESCAPED_UNICODE),
                            'lastupdate' => time()
                        );
                        DB::connection('laravel_destek')->table('cargos_track_numbers')->where('id', '=', $kargo->id)->update($track);
                    }
                    // kargo takip kontrol

                    if ($jsonTrack->durum == 2) {
                        if ($kargo->orderstatus != 26) {
                            $mesaj = '<a target="_blank" href="' . $jsonTrack->url . '">' . $jsonTrack->url . '</a> => İade olarak işaretle';
                            \App\Orders::updateOrderStatusbySystem($kargo->id, 26);
                        } else {
                            $mesaj = '<a target="_blank" href="' . $jsonTrack->url . '">' . $jsonTrack->url . '</a> => Siparişin durumu iade';
                        }
                    } elseif ($jsonTrack->durum == 1) {
                        if ($kargo->orderstatus != 15) {
                            $mesaj = '<a target="_blank" href="' . $jsonTrack->url . '">' . $jsonTrack->url . '</a> => Teslim olarak işaretle';
                            \App\Orders::updateOrderStatusbySystem($kargo->id, 15);
                        } else {
                            $mesaj = '<a target="_blank" href="' . $jsonTrack->url . '">' . $jsonTrack->url . '</a> => Siparişin durumu Teslim';
                        }
                    } elseif ($jsonTrack->durum == 4) {
                        if ($kargo->orderstatus != 3) {
                            $mesaj = '<a target="_blank" href="' . $jsonTrack->url . '">' . $jsonTrack->url . '</a> => Dağıtımda olarak işaretle';
                            \App\Orders::updateOrderStatusbySystem($kargo->id, 3);
                        } else {
                            $mesaj = '<a target="_blank" href="' . $jsonTrack->url . '">' . $jsonTrack->url . '</a> => Siparişin durumu Dağıtımda';
                        }
                    } elseif ($jsonTrack->durum == 3) {
                        if ($kargo->orderstatus != 21) {
                            $mesaj = '<a target="_blank" href="' . $jsonTrack->url . '">' . $jsonTrack->url . '</a> => Sorunlu olarak işaretle';
                            \App\Orders::updateOrderStatusbySystem($kargo->id, 21);
                        } else {
                            $mesaj = '<a target="_blank" href="' . $jsonTrack->url . '">' . $jsonTrack->url . '</a> => Siparişin durumu Sorunlu';
                        }
                    } elseif ($jsonTrack->durum == 6) {
                        if ($kargo->orderstatus != 21) {
                            $mesaj = '<a target="_blank" href="' . $jsonTrack->url . '">' . $jsonTrack->url . '</a> => Sorunlu olarak işaretle';
                            \App\Orders::updateOrderStatusbySystem($kargo->id, 21);
                        } else {
                            $mesaj = '<a target="_blank" href="' . $jsonTrack->url . '">' . $jsonTrack->url . '</a> => Siparişin durumu Sorunlu';
                        }
                    } else {
                        $mesaj = 'Durum tanımlaması gerekli => ' . $jsonTrack->durum;
                    }
                    DB::table('orders')->where('id', '=', $kargo->id)->update(['lastupdate' => time()]);
                } catch (\Exception $e) {
                    DB::table('orders')->where('id', '=', $kargo->id)->update(['lastupdate' => time()]);
                    //$xmlUrl = "http://webpostman.deposerileti.com:90/xml.asp?user=9500000268&password=BOSYKPBTYV&sipno=".$kargo->id;
                    //$getXmlIcerik = file_get_contents($xmlUrl);
                    //preg_match_all("@<durum>(.*?)</durum>@",$getXmlIcerik, $eslesen);
                    $mesaj = "KAYIT YOK!";
                    //echo "<pre>";
                    if (!empty($eslesen)) {
                        $xmlDurumKodu = @$eslesen[1][0];
                        if ($xmlDurumKodu == 2) {
                            if ($kargo->orderstatus != 26) {
                                $mesaj = ' => İade olarak işaretle';
                                \App\Orders::updateOrderStatusbySystem($kargo->id, 26);
                            } else {
                                $mesaj = ' => Siparişin durumu iade';
                            }
                        } elseif ($xmlDurumKodu == 1) {
                            if ($kargo->orderstatus != 15) {
                                $mesaj = ' => Teslim olarak işaretle';
                                \App\Orders::updateOrderStatusbySystem($kargo->id, 15);
                            } else {
                                $mesaj = ' => Siparişin durumu Teslim';
                            }
                        } elseif ($xmlDurumKodu == 4) {
                            if ($kargo->orderstatus != 3) {
                                $mesaj = ' => Dağıtımda olarak işaretle';
                                \App\Orders::updateOrderStatusbySystem($kargo->id, 3);
                            } else {
                                $mesaj = ' => Siparişin durumu Dağıtımda';
                            }
                        } else {
                            $mesaj = $xmlDurumKodu;
                        }
                        /* xml drum tanımlamaları */

                        $mesaj .= " / XML URL: {$xmlUrl} / GELEN KOD: {$xmlDurumKodu}";
                        //echo $trackInfo['details'];
                    }
                }
            } elseif ($kargo->type == 5) {
                $mesaj = 'YURTİÇİ KARGO';
                $ykPanelDurumKodu = 0;
                //echo "<pre>";
                $ykDurum = \App\Http\Controllers\BmyController::ykKargoSorgu($kargo->id);
//                echo "<pre>";
//                print_r($ykDurum->ShippingDeliveryVO->shippingDeliveryDetailVO->shippingDeliveryItemDetailVO->totalAmount);
//                exit();
//                echo "</pre>";
                //
                if ($ykDurum->ShippingDeliveryVO->count) {
                    $operationMessage = @$ykDurum->ShippingDeliveryVO->shippingDeliveryDetailVO->operationMessage;
                    $errKod = @$ykDurum->ShippingDeliveryVO->shippingDeliveryDetailVO->errCode;

                    // İşlem görmeyenler
                    if ($operationMessage == "Kargo İşlem Görmemiş.") {
                        DB::table('orders')->where('id', '=', $kargo->id)->update(['lastupdate' => time()]);
                        $mesaj = "Kargo İşlem Görmemiş";
                    }
                    // Kargo işlem görmüş, faturası henüz düzenlenmemiştir.
                    if ($operationMessage == "Kargo işlem görmüş, faturası henüz düzenlenmemiştir.") {
                        $mesaj = "Kargo işlem görmüş, faturası henüz düzenlenmemiştir.";
                    }
                    if ($errKod) {
                        $mesaj = $ykDurum->ShippingDeliveryVO->shippingDeliveryDetailVO->errMessage;
                        continue;
                    }

                    if (isset($ykDurum->ShippingDeliveryVO->shippingDeliveryDetailVO->shippingDeliveryItemDetailVO->cargoEventExplanation)) {
                        $cargoEvent = $ykDurum->ShippingDeliveryVO->shippingDeliveryDetailVO->shippingDeliveryItemDetailVO->cargoEventExplanation;
                        $cargoReasonExplanation = $ykDurum->ShippingDeliveryVO->shippingDeliveryDetailVO->shippingDeliveryItemDetailVO->cargoReasonExplanation;
                        $deliveryUnitName = $ykDurum->ShippingDeliveryVO->shippingDeliveryDetailVO->shippingDeliveryItemDetailVO->deliveryUnitName;
                        $trackingUrl = $ykDurum->ShippingDeliveryVO->shippingDeliveryDetailVO->shippingDeliveryItemDetailVO->trackingUrl;
                        $operationStatus = $ykDurum->ShippingDeliveryVO->shippingDeliveryDetailVO->operationStatus;
                        $operationMessage = $ykDurum->ShippingDeliveryVO->shippingDeliveryDetailVO->operationMessage;
                        $gonderiKodu = $ykDurum->ShippingDeliveryVO->shippingDeliveryDetailVO->shippingDeliveryItemDetailVO->docId;
                        $receiverInfo = (!empty($ykDurum->ShippingDeliveryVO->shippingDeliveryDetailVO->shippingDeliveryItemDetailVO->receiverInfo)) ? $ykDurum->ShippingDeliveryVO->shippingDeliveryDetailVO->shippingDeliveryItemDetailVO->receiverInfo : "";
                        $rejectStatusExplanation = (!empty($ykDurum->ShippingDeliveryVO->shippingDeliveryDetailVO->shippingDeliveryItemDetailVO->rejectStatusExplanation)) ? $ykDurum->ShippingDeliveryVO->shippingDeliveryDetailVO->shippingDeliveryItemDetailVO->rejectStatusExplanation : "";
                        $kargoBarkodu = $ykDurum->ShippingDeliveryVO->shippingDeliveryDetailVO->cargoKey;
                        // Teslim Edilmedi / Bekletiliyor Şubede
                        if ($cargoEvent == "Teslim Edilmedi / Bekletiliyor Şubede" && $operationStatus == "IND") {
                            $ykPanelDurumKodu = 21;
                            // Takip Bilgileri
                            $kargoKayit = array(
                                'gonderino' => $gonderiKodu,
                                'musteribarkod' => $kargoBarkodu,
                                'cikisno' => $gonderiKodu,
                                'durum' => $operationStatus,
                                'subekod' => $deliveryUnitName,
                                'ilce' => '',
                                'sube' => $deliveryUnitName,
                                'tutar' => '',
                                'url' => $trackingUrl
                            );
                            $track = array(
                                'order_id' => $kargo->id,
                                'cargo_type' => 5,
                                'details' => json_encode($kargoKayit, JSON_UNESCAPED_UNICODE),
                                'lastupdate' => time()
                            );
                            $takipNoVarmi = DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->count();
                            if ($takipNoVarmi) {
                                //echo '<b style="color:#341f97">Kargo takip bilgileri güncellendi.</b>';
                                //echo '<br />';
                                DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->update($track);
                            } else {
                                DB::connection('laravel_destek')->table('cargos_track_numbers')->insertGetId($track);
                                //echo '<b style="color:#341f97">Kargo takip bilgileri eklendi.</b>';
                                //echo '<br />';
                            }
                            // Takip Bilgileri
                        }
                        // Teslim Edildi
                        if ($cargoEvent == "Teslim Edildi" && $operationStatus == "DLV" && $operationMessage == "Kargo teslim edilmiştir." && $deliveryUnitName != "KAĞITHANE") {
                            $ykPanelDurumKodu = 15;
                            // Takip Bilgileri
                            $kargoKayit = array(
                                'gonderino' => $gonderiKodu,
                                'musteribarkod' => $kargoBarkodu,
                                'cikisno' => $gonderiKodu,
                                'durum' => $operationStatus,
                                'subekod' => $deliveryUnitName,
                                'ilce' => '',
                                'sube' => $deliveryUnitName,
                                'tutar' => '',
                                'url' => $trackingUrl
                            );
                            $track = array(
                                'order_id' => $kargo->id,
                                'cargo_type' => 5,
                                'details' => json_encode($kargoKayit, JSON_UNESCAPED_UNICODE),
                                'lastupdate' => time()
                            );
                            $takipNoVarmi = DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->count();
                            if ($takipNoVarmi) {
                                //echo '<b style="color:#341f97">Kargo takip bilgileri güncellendi.</b>';
                                //echo '<br />';
                                DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->update($track);
                            } else {
                                //DB::connection('laravel_destek')->table('cargos_track_numbers')->insertGetId($track);
                                //echo '<b style="color:#341f97">Kargo takip bilgileri eklendi.</b>';
                                //echo '<br />';
                            }
                            // Takip Bilgileri
                        }
                        // İade
                        if ($cargoEvent == "Teslim Edildi" && $operationStatus == "DLV" && $operationMessage == "Kargo teslim edilmiştir." && $deliveryUnitName == "KAĞITHANE" && $receiverInfo == "İADE ") {
                            $mesaj = "İade 274";
                            $ykPanelDurumKodu = 26;
                            // Takip Bilgileri
                            $kargoKayit = array(
                                'gonderino' => $gonderiKodu,
                                'musteribarkod' => $kargoBarkodu,
                                'cikisno' => $gonderiKodu,
                                'durum' => $operationStatus,
                                'subekod' => $deliveryUnitName,
                                'ilce' => '',
                                'sube' => $deliveryUnitName,
                                'tutar' => '',
                                'url' => $trackingUrl
                            );
                            $track = array(
                                'order_id' => $kargo->id,
                                'cargo_type' => 5,
                                'details' => json_encode($kargoKayit, JSON_UNESCAPED_UNICODE),
                                'lastupdate' => time()
                            );
                            $takipNoVarmi = DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->count();
                            if ($takipNoVarmi) {
                                //echo '<b style="color:#341f97">Kargo takip bilgileri güncellendi.</b>';
                                //echo '<br />';
                                DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->update($track);
                            } else {
                                //DB::connection('laravel_destek')->table('cargos_track_numbers')->insertGetId($track);
                                //echo '<b style="color:#341f97">Kargo takip bilgileri eklendi.</b>';
                                //echo '<br />';
                            }
                            // Takip Bilgileri
                        }
                        if ($cargoEvent == "Teslim Edildi" && $operationStatus == "DLV" && $operationMessage == "İade İsteği Alınmıştır." && $deliveryUnitName == "KAĞITHANE" && $receiverInfo == "134049 KAYIP ARAŞTIRMA") {
                            $mesaj = "İade 307";
                            $ykPanelDurumKodu = 26;
                            // Takip Bilgileri
                            $kargoKayit = array(
                                'gonderino' => $gonderiKodu,
                                'musteribarkod' => $kargoBarkodu,
                                'cikisno' => $gonderiKodu,
                                'durum' => $operationStatus,
                                'subekod' => $deliveryUnitName,
                                'ilce' => '',
                                'sube' => $deliveryUnitName,
                                'tutar' => '',
                                'url' => $trackingUrl
                            );
                            $track = array(
                                'order_id' => $kargo->id,
                                'cargo_type' => 5,
                                'details' => json_encode($kargoKayit, JSON_UNESCAPED_UNICODE),
                                'lastupdate' => time()
                            );
                            $takipNoVarmi = DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->count();
                            if ($takipNoVarmi) {
                                //echo '<b style="color:#341f97">Kargo takip bilgileri güncellendi.</b>';
                                //echo '<br />';
                                DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->update($track);
                            } else {
                                //DB::connection('laravel_destek')->table('cargos_track_numbers')->insertGetId($track);
                                //echo '<b style="color:#341f97">Kargo takip bilgileri eklendi.</b>';
                                //echo '<br />';
                            }
                            // Takip Bilgileri
                        }
                        if ($cargoEvent == "Teslim Edildi" && $operationStatus == "DLV" && $operationMessage == "Kargo teslim edilmiştir." && $deliveryUnitName == "KAĞITHANE" && $rejectStatusExplanation == "İade Sonlandırıldı") {
                            $mesaj = "İade 340";
                            //print_r($ykDurum);
                            $ykPanelDurumKodu = 26;
                            // Takip Bilgileri
                            $kargoKayit = array(
                                'gonderino' => $gonderiKodu,
                                'musteribarkod' => $kargoBarkodu,
                                'cikisno' => $gonderiKodu,
                                'durum' => $operationStatus,
                                'subekod' => $deliveryUnitName,
                                'ilce' => '',
                                'sube' => $deliveryUnitName,
                                'tutar' => '',
                                'url' => $trackingUrl
                            );
                            $track = array(
                                'order_id' => $kargo->id,
                                'cargo_type' => 5,
                                'details' => json_encode($kargoKayit, JSON_UNESCAPED_UNICODE),
                                'lastupdate' => time()
                            );
                            $takipNoVarmi = DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->count();
                            if ($takipNoVarmi) {
                                //echo '<b style="color:#341f97">Kargo takip bilgileri güncellendi.</b>';
                                //echo '<br />';
                                DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->update($track);
                            } else {
                                //DB::connection('laravel_destek')->table('cargos_track_numbers')->insertGetId($track);
                                //echo '<b style="color:#341f97">Kargo takip bilgileri eklendi.</b>';
                                //echo '<br />';
                            }
                            // Takip Bilgileri
                        }
                        // Gönderi Toplandı
                        if ($cargoEvent == "Gönderi Toplandı" && $operationStatus == "IND") {
                            $mesaj = "Gönderi Toplandı";
                            $ykPanelDurumKodu = 9;
                            // Takip Bilgileri
                            $kargoKayit = array(
                                'gonderino' => $gonderiKodu,
                                'musteribarkod' => $kargoBarkodu,
                                'cikisno' => $gonderiKodu,
                                'durum' => $operationStatus,
                                'subekod' => $deliveryUnitName,
                                'ilce' => '',
                                'sube' => $deliveryUnitName,
                                'tutar' => '',
                                'url' => $trackingUrl
                            );
                            $track = array(
                                'order_id' => $kargo->id,
                                'cargo_type' => 5,
                                'details' => json_encode($kargoKayit, JSON_UNESCAPED_UNICODE),
                                'lastupdate' => time()
                            );
                            $takipNoVarmi = DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->count();
                            if ($takipNoVarmi) {
                                //echo '<b style="color:#341f97">Kargo takip bilgileri güncellendi.</b>';
                                //echo '<br />';
                                DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->update($track);
                            } else {
                                //DB::connection('laravel_destek')->table('cargos_track_numbers')->insertGetId($track);
                                ////echo '<b style="color:#341f97">Kargo takip bilgileri eklendi.</b>';
                                //echo '<br />';
                            }
                            // Takip Bilgileri
                        }
                        // Kuryeye Zimmetlendi (dağıtıma çıktı)
                        if ($cargoReasonExplanation == "Kuryede/zimmetlendi (dağıtıma çıktı)" && $cargoEvent == "Kargo Yüklendi" && $operationStatus == "IND") {
                            $ykPanelDurumKodu = 3;
                            // Takip Bilgileri
                            $kargoKayit = array(
                                'gonderino' => $gonderiKodu,
                                'musteribarkod' => $kargoBarkodu,
                                'cikisno' => $gonderiKodu,
                                'durum' => $operationStatus,
                                'subekod' => $deliveryUnitName,
                                'ilce' => '',
                                'sube' => $deliveryUnitName,
                                'tutar' => '',
                                'url' => $trackingUrl
                            );
                            $track = array(
                                'order_id' => $kargo->id,
                                'cargo_type' => 5,
                                'details' => json_encode($kargoKayit, JSON_UNESCAPED_UNICODE),
                                'lastupdate' => time()
                            );
                            $takipNoVarmi = DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->count();
                            if ($takipNoVarmi) {
                                //echo '<b style="color:#341f97">Kargo takip bilgileri güncellendi.</b>';
                                //echo '<br />';
                                DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->update($track);
                            } else {
                                DB::connection('laravel_destek')->table('cargos_track_numbers')->insertGetId($track);
                                //echo '<b style="color:#341f97">Kargo takip bilgileri eklendi.</b>';
                                //echo '<br />';
                            }
                            // Takip Bilgileri
                        }
                        if ($cargoReasonExplanation == "Sorun Yok" && $cargoEvent == "Kargo Yüklendi" && $operationStatus == "IND") {
                            $ykPanelDurumKodu = 3;
                            // Takip Bilgileri
                            $kargoKayit = array(
                                'gonderino' => $gonderiKodu,
                                'musteribarkod' => $kargoBarkodu,
                                'cikisno' => $gonderiKodu,
                                'durum' => $operationStatus,
                                'subekod' => $deliveryUnitName,
                                'ilce' => '',
                                'sube' => $deliveryUnitName,
                                'tutar' => '',
                                'url' => $trackingUrl
                            );
                            $track = array(
                                'order_id' => $kargo->id,
                                'cargo_type' => 5,
                                'details' => json_encode($kargoKayit, JSON_UNESCAPED_UNICODE),
                                'lastupdate' => time()
                            );
                            $takipNoVarmi = DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->count();
                            if ($takipNoVarmi) {
                                //echo '<b style="color:#341f97">Kargo takip bilgileri güncellendi.</b>';
                                //echo '<br />';
                                DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->update($track);
                            } else {
                                DB::connection('laravel_destek')->table('cargos_track_numbers')->insertGetId($track);
                                //echo '<b style="color:#341f97">Kargo takip bilgileri eklendi.</b>';
                                //echo '<br />';
                            }
                            // Takip Bilgileri
                        }
                        if ($cargoReasonExplanation == "Sorun Yok" && $cargoEvent == "Kargo İndirildi" && $operationStatus == "IND") {
                            $ykPanelDurumKodu = 3;
                            // Takip Bilgileri
                            $kargoKayit = array(
                                'gonderino' => $gonderiKodu,
                                'musteribarkod' => $kargoBarkodu,
                                'cikisno' => $gonderiKodu,
                                'durum' => $operationStatus,
                                'subekod' => $deliveryUnitName,
                                'ilce' => '',
                                'sube' => $deliveryUnitName,
                                'tutar' => '',
                                'url' => $trackingUrl
                            );
                            $track = array(
                                'order_id' => $kargo->id,
                                'cargo_type' => 5,
                                'details' => json_encode($kargoKayit, JSON_UNESCAPED_UNICODE),
                                'lastupdate' => time()
                            );
                            $takipNoVarmi = DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->count();
                            if ($takipNoVarmi) {
                                //echo '<b style="color:#341f97">Kargo takip bilgileri güncellendi.</b>';
                                //echo '<br />';
                                DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->update($track);
                            } else {
                                DB::connection('laravel_destek')->table('cargos_track_numbers')->insertGetId($track);
                                //echo '<b style="color:#341f97">Kargo takip bilgileri eklendi.</b>';
                                //echo '<br />';
                            }
                            // Takip Bilgileri
                        }
                        // Sorunlu
                        if ($cargoReasonExplanation == "Alıcı Adreste Bulunamadı. Not Bırakıldı" && $cargoEvent == "Borçlandırma" && $operationStatus == "IND") {
                            $ykPanelDurumKodu = 21;
                            // Takip Bilgileri
                            $kargoKayit = array(
                                'gonderino' => $gonderiKodu,
                                'musteribarkod' => $kargoBarkodu,
                                'cikisno' => $gonderiKodu,
                                'durum' => $operationStatus,
                                'subekod' => $deliveryUnitName,
                                'ilce' => '',
                                'sube' => $deliveryUnitName,
                                'tutar' => '',
                                'url' => $trackingUrl
                            );
                            $track = array(
                                'order_id' => $kargo->id,
                                'cargo_type' => 5,
                                'details' => json_encode($kargoKayit, JSON_UNESCAPED_UNICODE),
                                'lastupdate' => time()
                            );
                            $takipNoVarmi = DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->count();
                            if ($takipNoVarmi) {
                                //echo '<b style="color:#341f97">Kargo takip bilgileri güncellendi.</b>';
                                //echo '<br />';
                                DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->update($track);
                            } else {
                                DB::connection('laravel_destek')->table('cargos_track_numbers')->insertGetId($track);
                                //echo '<b style="color:#341f97">Kargo takip bilgileri eklendi.</b>';
                                //echo '<br />';
                            }
                            // Takip Bilgileri
                        }
                        if ($cargoReasonExplanation == "Alıcı Kabul Etmedi (Ücret, Ürün Bedeli, Eksik İrsaliye/Fatura  vb. Nedenlerle)" && $cargoEvent == "Borçlandırma" && $operationStatus == "IND") {
                            $ykPanelDurumKodu = 21;
                            // Takip Bilgileri
                            $kargoKayit = array(
                                'gonderino' => $gonderiKodu,
                                'musteribarkod' => $kargoBarkodu,
                                'cikisno' => $gonderiKodu,
                                'durum' => $operationStatus,
                                'subekod' => $deliveryUnitName,
                                'ilce' => '',
                                'sube' => $deliveryUnitName,
                                'tutar' => '',
                                'url' => $trackingUrl
                            );
                            $track = array(
                                'order_id' => $kargo->id,
                                'cargo_type' => 5,
                                'details' => json_encode($kargoKayit, JSON_UNESCAPED_UNICODE),
                                'lastupdate' => time()
                            );
                            $takipNoVarmi = DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->count();
                            if ($takipNoVarmi) {
                                //echo '<b style="color:#341f97">Kargo takip bilgileri güncellendi.</b>';
                                //echo '<br />';
                                DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->update($track);
                            } else {
                                DB::connection('laravel_destek')->table('cargos_track_numbers')->insertGetId($track);
                                //echo '<b style="color:#341f97">Kargo takip bilgileri eklendi.</b>';
                                //echo '<br />';
                            }
                            // Takip Bilgileri
                        }
                        if ($cargoReasonExplanation == "Varış Merkezi Hatası" && $cargoEvent == "Borçlandırma" && $operationStatus == "IND") {
                            $ykPanelDurumKodu = 21;
                            // Takip Bilgileri
                            $kargoKayit = array(
                                'gonderino' => $gonderiKodu,
                                'musteribarkod' => $kargoBarkodu,
                                'cikisno' => $gonderiKodu,
                                'durum' => $operationStatus,
                                'subekod' => $deliveryUnitName,
                                'ilce' => '',
                                'sube' => $deliveryUnitName,
                                'tutar' => '',
                                'url' => $trackingUrl
                            );
                            $track = array(
                                'order_id' => $kargo->id,
                                'cargo_type' => 5,
                                'details' => json_encode($kargoKayit, JSON_UNESCAPED_UNICODE),
                                'lastupdate' => time()
                            );
                            $takipNoVarmi = DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->count();
                            if ($takipNoVarmi) {
                                //echo '<b style="color:#341f97">Kargo takip bilgileri güncellendi.</b>';
                                //echo '<br />';
                                DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->update($track);
                            } else {
                                DB::connection('laravel_destek')->table('cargos_track_numbers')->insertGetId($track);
                                //echo '<b style="color:#341f97">Kargo takip bilgileri eklendi.</b>';
                                //echo '<br />';
                            }
                            // Takip Bilgileri
                        }
                        if ($cargoReasonExplanation == "Yanlış Yükleme" && $cargoEvent == "Kargo İndirildi" && $operationStatus == "IND") {
                            $ykPanelDurumKodu = 21;
                            // Takip Bilgileri
                            $kargoKayit = array(
                                'gonderino' => $gonderiKodu,
                                'musteribarkod' => $kargoBarkodu,
                                'cikisno' => $gonderiKodu,
                                'durum' => $operationStatus,
                                'subekod' => $deliveryUnitName,
                                'ilce' => '',
                                'sube' => $deliveryUnitName,
                                'tutar' => '',
                                'url' => $trackingUrl
                            );
                            $track = array(
                                'order_id' => $kargo->id,
                                'cargo_type' => 5,
                                'details' => json_encode($kargoKayit, JSON_UNESCAPED_UNICODE),
                                'lastupdate' => time()
                            );
                            $takipNoVarmi = DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->count();
                            if ($takipNoVarmi) {
                                //echo '<b style="color:#341f97">Kargo takip bilgileri güncellendi.</b>';
                                //echo '<br />';
                                DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->update($track);
                            } else {
                                DB::connection('laravel_destek')->table('cargos_track_numbers')->insertGetId($track);
                                //echo '<b style="color:#341f97">Kargo takip bilgileri eklendi.</b>';
                                //echo '<br />';
                            }
                            // Takip Bilgileri
                        }
                        if ($cargoReasonExplanation == "Fatura Üzerindeki Adres Farklı" && $cargoEvent == "Borçlandırma" && $operationStatus == "IND") {
                            $ykPanelDurumKodu = 21;
                            // Takip Bilgileri
                            $kargoKayit = array(
                                'gonderino' => $gonderiKodu,
                                'musteribarkod' => $kargoBarkodu,
                                'cikisno' => $gonderiKodu,
                                'durum' => $operationStatus,
                                'subekod' => $deliveryUnitName,
                                'ilce' => '',
                                'sube' => $deliveryUnitName,
                                'tutar' => '',
                                'url' => $trackingUrl
                            );
                            $track = array(
                                'order_id' => $kargo->id,
                                'cargo_type' => 5,
                                'details' => json_encode($kargoKayit, JSON_UNESCAPED_UNICODE),
                                'lastupdate' => time()
                            );
                            $takipNoVarmi = DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->count();
                            if ($takipNoVarmi) {
                                //echo '<b style="color:#341f97">Kargo takip bilgileri güncellendi.</b>';
                                //echo '<br />';
                                DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->update($track);
                            } else {
                                DB::connection('laravel_destek')->table('cargos_track_numbers')->insertGetId($track);
                                //echo '<b style="color:#341f97">Kargo takip bilgileri eklendi.</b>';
                                //echo '<br />';
                            }
                            // Takip Bilgileri
                        }
                        if ($cargoReasonExplanation == "Adres Sorunu Nedeniyle Alıcıya Ulaşılamadı" && $cargoEvent == "Borçlandırma" && $operationStatus == "IND") {
                            $ykPanelDurumKodu = 21;
                            // Takip Bilgileri
                            $kargoKayit = array(
                                'gonderino' => $gonderiKodu,
                                'musteribarkod' => $kargoBarkodu,
                                'cikisno' => $gonderiKodu,
                                'durum' => $operationStatus,
                                'subekod' => $deliveryUnitName,
                                'ilce' => '',
                                'sube' => $deliveryUnitName,
                                'tutar' => '',
                                'url' => $trackingUrl
                            );
                            $track = array(
                                'order_id' => $kargo->id,
                                'cargo_type' => 5,
                                'details' => json_encode($kargoKayit, JSON_UNESCAPED_UNICODE),
                                'lastupdate' => time()
                            );
                            $takipNoVarmi = DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->count();
                            if ($takipNoVarmi) {
                                //echo '<b style="color:#341f97">Kargo takip bilgileri güncellendi.</b>';
                                //echo '<br />';
                                DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->update($track);
                            } else {
                                DB::connection('laravel_destek')->table('cargos_track_numbers')->insertGetId($track);
                                //echo '<b style="color:#341f97">Kargo takip bilgileri eklendi.</b>';
                                //echo '<br />';
                            }
                            // Takip Bilgileri
                        }
                        if ($cargoReasonExplanation == "Müşteri İsteği" && $cargoEvent == "Borçlandırma" && $operationStatus == "IND") {
                            $ykPanelDurumKodu = 21;
                            // Takip Bilgileri
                            $kargoKayit = array(
                                'gonderino' => $gonderiKodu,
                                'musteribarkod' => $kargoBarkodu,
                                'cikisno' => $gonderiKodu,
                                'durum' => $operationStatus,
                                'subekod' => $deliveryUnitName,
                                'ilce' => '',
                                'sube' => $deliveryUnitName,
                                'tutar' => '',
                                'url' => $trackingUrl
                            );
                            $track = array(
                                'order_id' => $kargo->id,
                                'cargo_type' => 5,
                                'details' => json_encode($kargoKayit, JSON_UNESCAPED_UNICODE),
                                'lastupdate' => time()
                            );
                            $takipNoVarmi = DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->count();
                            if ($takipNoVarmi) {
                                //echo '<b style="color:#341f97">Kargo takip bilgileri güncellendi.</b>';
                                //echo '<br />';
                                DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->update($track);
                            } else {
                                DB::connection('laravel_destek')->table('cargos_track_numbers')->insertGetId($track);
                                //echo '<b style="color:#341f97">Kargo takip bilgileri eklendi.</b>';
                                //echo '<br />';
                            }
                            // Takip Bilgileri
                        }
                    }

                    // Durum belirleme
                    if ($ykPanelDurumKodu == 15) {
                        if ($kargo->orderstatus != 15) {
                            $mesaj = '<a target="_blank" href="' . @$trackingUrl . '">' . @$trackingUrl . '</a> => Teslim olarak işaretle';
                            \App\Orders::updateOrderStatusbySystem($kargo->id, 15);
                        } else {
                            $mesaj = '<a target="_blank" href="' . @$trackingUrl . '">' . @$trackingUrl . '</a> => Sipariş durumu teslim';
                        }
                    }
                    if ($ykPanelDurumKodu == 21) {
                        if ($kargo->orderstatus != 21) {
                            $mesaj = '<a target="_blank" href="' . @$trackingUrl . '">' . @$trackingUrl . '</a> => Sorunlu olarak işaretle';
                            \App\Orders::updateOrderStatusbySystem($kargo->id, 21);
                        } else {
                            $mesaj = '<a target="_blank" href="' . @$trackingUrl . '">' . @$trackingUrl . '</a> => Sipariş durumu sorunlu';
                        }
                    }
                    if ($ykPanelDurumKodu == 26) {
                        if ($kargo->orderstatus != 26) {
                            $mesaj = '<a target="_blank" href="' . @$trackingUrl . '">' . @$trackingUrl . '</a> => İade olarak işaretle';
                            \App\Orders::updateOrderStatusbySystem($kargo->id, 26);
                        } else {
                            $mesaj = '<a target="_blank" href="' . @$trackingUrl . '">' . @$trackingUrl . '</a> => Sipariş durumu İade';
                        }
                    }
                    if ($ykPanelDurumKodu == 3) {
                        if ($kargo->orderstatus != 3) {
                            $mesaj = '<a target="_blank" href="' . @$trackingUrl . '">' . @$trackingUrl . '</a> => Dağıtımda olarak işaretle';
                            \App\Orders::updateOrderStatusbySystem($kargo->id, 3);
                        } else {
                            $mesaj = '<a target="_blank" href="' . @$trackingUrl . '">' . @$trackingUrl . '</a> => Sipariş durumu dağıtımda';
                        }
                    }

                    if ($ykPanelDurumKodu) {
                        $kargoTutari = $ykDurum->ShippingDeliveryVO->shippingDeliveryDetailVO->shippingDeliveryItemDetailVO->totalAmount;
                        if (!empty($kargoTutari)) {
                            DB::table("orders")->where("id", "=", $kargo->id)->update(["kargo_maliyet" => $kargoTutari]);
                        }
                        DB::table('orders')->where('id', '=', $kargo->id)->update(['lastupdate' => time()]);
                    }
                }
                if ($mesaj == "YURTİÇİ KARGO") {
                    echo "<pre>";
                    print_r($ykDurum);
                    DB::table('orders')->where('id', '=', $kargo->id)->update(['lastupdate' => time()]);
                    exit();
                    echo "</pre>";
                    //exit();
                }
                //
                //print_r($ykDurum);

                //DB::table('orders')->where('id','=',$kargo->id)->update(['lastupdate'=>time()]);
            } elseif ($kargo->type == 2) {
                $url = 'http://pan.kuryewo.com/api/rest/list?siparisno=' . $kargo->id;
                //
                $client = new \GuzzleHttp\Client();
                $request = $client->request('GET', $url);
                $reqJson = json_decode($request->getBody());
                if (!empty($reqJson->toplam)) {
                    // kargo takip kontrol
                    $takipKontrol = DB::connection('laravel_destek')->table('cargos_track_numbers')->where('order_id', '=', $kargo->id)->count();
                    if (!$takipKontrol) {
                        $gonderiDetay = array(
                            'gonderino' => $reqJson->data[0]->id,
                            'musteribarkod' => $reqJson->data[0]->musteri_barkod,
                            'cikisno' => $reqJson->data[0]->id,
                            'durum' => $reqJson->data[0]->durum_kodu,
                            'subekod' => 'KARGOWO',
                            'sube' => 'KARGOWO',
                            'tutar' => '',
                            'url' => $reqJson->data[0]->takip_url
                        );
                        $track = array(
                            'order_id' => $kargo->id,
                            'cargo_type' => 5,
                            'details' => json_encode($gonderiDetay, JSON_UNESCAPED_UNICODE),
                            'lastupdate' => time()
                        );
                        DB::connection('laravel_destek')->table('cargos_track_numbers')->insertGetId($track);
                    } else {
                        $gonderiDetay = array(
                            'gonderino' => $reqJson->data[0]->id,
                            'musteribarkod' => $reqJson->data[0]->musteri_barkod,
                            'cikisno' => $reqJson->data[0]->id,
                            'durum' => $reqJson->data[0]->durum_kodu,
                            'subekod' => 'KARGOWO',
                            'sube' => 'KARGOWO',
                            'tutar' => '',
                            'url' => $reqJson->data[0]->takip_url
                        );
                        $track = array(
                            'order_id' => $kargo->id,
                            'cargo_type' => 5,
                            'details' => json_encode($gonderiDetay, JSON_UNESCAPED_UNICODE),
                            'lastupdate' => time()
                        );
                        DB::connection('laravel_destek')->table('cargos_track_numbers')->where('id', '=', $kargo->id)->update($track);
                    }
                    // kargo takip kontrol

                    if ($reqJson->data[0]->durum_kodu == 4) {
                        if ($kargo->orderstatus != 15) {
                            $mesaj = '<a target="_blank" href="' . $reqJson->data[0]->takip_url . '">' . $reqJson->data[0]->takip_url . '</a> => Teslim olarak işaretle';
                            \App\Orders::updateOrderStatusbySystem($kargo->id, 15);
                        } else {
                            $mesaj = '<a target="_blank" href="' . $reqJson->data[0]->takip_url . '">' . $reqJson->data[0]->url . '</a> => Sipariş durumu teslim';
                        }
                    } elseif ($reqJson->data[0]->durum_kodu == 8) {
                        if ($kargo->orderstatus != 3) {
                            $mesaj = '<a target="_blank" href="' . $reqJson->data[0]->takip_url . '">' . $reqJson->data[0]->takip_url . '</a> => Dağıtımda olarak işaretle';
                            \App\Orders::updateOrderStatusbySystem($kargo->id, 3);
                        } else {
                            $mesaj = '<a target="_blank" href="' . $reqJson->data[0]->takip_url . '">' . $reqJson->data[0]->takip_url . '</a> => Sipariş durumu dağıtımda';
                        }
                    } elseif ($reqJson->data[0]->durum_kodu == 5) {
                        if ($kargo->orderstatus != 16) {
                            $mesaj = '<a target="_blank" href="' . $reqJson->data[0]->takip_url . '">' . $reqJson->data[0]->takip_url . '</a> => İade olarak işaretle';
                            \App\Orders::updateOrderStatusbySystem($kargo->id, 16);
                        } else {
                            $mesaj = '<a target="_blank" href="' . $reqJson->data[0]->takip_url . '">' . $reqJson->data[0]->takip_url . '</a> => Sipariş durumu iade';
                        }
                    } else {
                        $mesaj = "Durum tanımlaması gerekli / {$reqJson->data[0]->durum} => {$reqJson->data[0]->durum_kodu}";
                    }
                } else {
                    $mesaj = 'Data bulunamadı';
                }
                //
                //$mesaj = 'KARGOWO';
                DB::table('orders')->where('id', '=', $kargo->id)->update(['lastupdate' => time()]);
            } else {
                $mesaj = 'Kargo tipi tanımlaması gerekli';
            }
            echo '<tr>';
            echo '<td>' . $kargo->id . '</td>';
            echo '<td>' . date('d-m-Y H:i:s', $kargo->time) . '</td>';
            echo '<td>' . $kargo->name . '  '.$kargo->phone.'</td>';
            echo '<td>' . $kargo->cargo . ' (' . $kargo->c_id . ')</td>';
            echo '<td>' . $kargo->subcargo . ' (' . $kargo->sc_id . ')</td>';
            echo '<td>' . $kargo->ost . '</td>';
            echo '<td>' . $kargoTipi . '</td>';
            echo '<td>' . $mesaj . '</td>';
            echo '<td>' . date('d-m-Y H:i:s', $kargo->lastupdate) . '</td>';
            echo '</tr>';
            //exit();
        endforeach;
        echo '</table>';
        echo '</center>';
    }
}
