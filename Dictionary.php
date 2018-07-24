<?php

namespace modules\deposit;
    /**
     * A dictionary class for Deposit
     *
     * @package    Dictionary Deposit
     * @author     M ABD AZIZ ALFIAN <github.com/aalfiann>
     * @copyright  Copyright (c) 2018 M ABD AZIZ ALFIAN
     * @license    https://github.com/aalfiann/reSlim-modules-deposit/blob/master/LICENSE.md  MIT License
     */
	class Dictionary {
        /**
         * @param $id is indonesian dictionary
         *
         */
        public static $id = [
            //Transaction process
            'default_desc_topup' => 'Pengisian topup deposit',
            'default_desc_withdrawal' => 'Penarikan dana deposit',
            'default_desc_transfer' => 'Transfer dana deposit',
            //handler
            'DP101' => 'Pengisian topup berhasil!',
            'DP102' => 'Penarikan dana deposit berhasil!',
            'DP103' => 'Transfer dana deposit berhasil!',
            'DP104' => 'Transaksi berhasil!',
            'DP201' => 'Pengisian topup gagal!',
            'DP202' => 'Penarikan dana deposit gagal!',
            'DP203' => 'Transfer dana deposit gagal!',
            'DP204' => 'No Deposit ID tujuan salah atau tidak ditemukan!',
            'DP205' => 'No Deposit ID Anda salah atau tidak ditemukan!',
            'DP206' => 'Anda tidak memiliki saldo!',
            'DP207' => 'Saldo Anda tidak mencukupi!',
            'DP301' => 'Generate ReferenceID berhasil!'
        ];

        /**
         * @param $en is english dictionary
         *
         */
        public static $en = [
            //Transaction process
            'default_desc_topup' => 'Charging topup deposit',
            'default_desc_withdrawal' => 'Withdrawal deposit',
            'default_desc_transfer' => 'Transfer deposit',
            //handler
            'DP101' => 'Topup successful!',
            'DP102' => 'Withdrawal successful!',
            'DP103' => 'Transfer successful!',
            'DP104' => 'Transaction successful!',
            'DP201' => 'Charging topup failed!',
            'DP202' => 'Withdrawal failed!',
            'DP203' => 'Transfer failed!',
            'DP204' => 'Deposit ID destination is incorrect or not found!',
            'DP205' => 'Your Deposit ID is incorrect or not found!',
            'DP206' => 'You have no balance!',
            'DP207' => 'Your balance is not enough!',
            'DP301' => 'Generate ReferenceID successful!'
        ];

        /**
         * @param $key : input the key of dictionary
         * @return string dictionary language
         */
        public static function write($key,$lang=''){
            switch($lang){
                case 'id':
                    return self::$id[$key];
                break;
                default:
                    return self::$en[$key];
            }
        }
    }