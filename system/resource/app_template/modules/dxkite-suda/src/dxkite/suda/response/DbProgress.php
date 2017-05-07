<?php
namespace dxkite\suda\response;

use suda\core\Session;
use suda\core\Cookie;
use suda\core\Request;
use suda\core\Query;
use dxkite\suda\DBManager;

/**
* visit url /database-process as all method to run this class.
* you call use u('datebase_progress',Array) to create path.
* @template: default:db_progress.tpl.html
* @name: datebase_progress
* @url: /database-process
* @param:
*/
class DbProgress extends \suda\core\Response
{
    public function onRequest(Request $request)
    {
        $this->type('html');
        // 操作
        $option=strtolower($request->get()->option);
        // 操作全部
        $all=strtolower($request->get()->all('no'));
        if ($name=$request->get()->name) {
            DBManager::getInstance()->archive($name);
        }
        // 操作全部
        if ($all==='yes') {
            if ($option==='recovery') {
                DBManager::getInstance()->createTables()->importTables();
            } elseif ($option==='delete') {
                DBManager::getInstance()->deleteTables();
            } elseif ($option==='backup') {
                DBManager::getInstance()->archive(time())->backupTables();
            } elseif ($option==='refresh') {
                DBManager::getInstance()->parseDTOs()->createTables();
            }
        } else {
            // 操作多个模块
            if ($request->isPost()) {
                $select=$request->post()->select([]);
                _D()->info($select);
                $tables=array_keys($select);
                if ($option==='recovery') {
                    DBManager::getInstance()->createTables($tables)->importTables($tables);
                } elseif ($option==='delete') {
                    DBManager::getInstance()->deleteTables($tables);
                } elseif ($option==='backup') {
                    DBManager::getInstance()->backupTables($tables);
                } elseif ($option==='refresh') {
                    DBManager::getInstance()->parseDTOs($tables)->createTables($tables);
                }
            }
            // 操作单个模块
            elseif ($module=$request->get()->module) {
                $tables=[$module];
                if ($option==='recovery') {
                    DBManager::getInstance()->createTables($tables)->importTables($tables);
                } elseif ($option==='delete') {
                    DBManager::getInstance()->deleteTables($tables);
                } elseif ($option==='backup') {
                    DBManager::getInstance()->backupTables($tables);
                } elseif ($option==='refresh') {
                    DBManager::getInstance()->parseDTOs($tables)->createTables($tables);
                }
            }
        }
    }
}