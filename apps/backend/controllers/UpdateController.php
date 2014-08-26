<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * UpdateController
 * 
 * Handles the actions for updating mailwizz ema
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.1
 */
 
class UpdateController extends Controller
{
    /**
     * Display the update page
     */
    public function actionIndex()
    {
        $request = Yii::app()->request;
        $options = Yii::app()->options;
        $notify  = Yii::app()->notify;
        
        $versionInFile  = MW_VERSION;
        $versionInDb    = $options->get('system.common.version');
        
        if (!version_compare($versionInFile, $versionInDb, '>')) {
            if ($options->get('system.common.site_status', 'online') != 'online') {
                $options->set('system.common.site_status', 'online');
            }
            $this->redirect(array('dashboard/index'));
        }
        
        // start the work
        if ($request->isPostRequest) {
            
            // make sure we have both, time and memory...
            set_time_limit(0);
            ini_set('memory_limit', -1);
            
            $workersPath = Yii::getPathOfAlias('backend.components.update');
            require_once $workersPath . '/UpdateWorkerAbstract.php';
            
            $updateWorkers  = (array)FileSystemHelper::readDirectoryContents($workersPath);
            
            foreach ($updateWorkers as $index => $fileName) {
                $fileName = basename($fileName, '.php');
                if (strpos($fileName, 'UpdateWorkerFor_') !== 0) {
                    unset($updateWorkers[$index]);
                    continue;
                }
                
                $workerVersion = str_replace('UpdateWorkerFor_', '', $fileName);
                $workerVersion = str_replace('_', '.', $workerVersion);
                
                // previous versions ?
                if (version_compare($workerVersion, $versionInDb, '<=')) {
                    unset($updateWorkers[$index]);
                    continue;
                }
                
                // next versions ?
                if (version_compare($workerVersion, $versionInFile, '>')) {
                    unset($updateWorkers[$index]);
                    continue;
                }
                
                $updateWorkers[$index] = $workerVersion;
            }
            
            sort($updateWorkers, SORT_NUMERIC | SORT_ASC);
            
            $db = Yii::app()->getDb();
            $db->createCommand('SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0')->execute();
            $db->createCommand('SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0')->execute();
            $db->createCommand('SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE=""')->execute();
        
            $transaction = $db->beginTransaction();
            try {
                foreach ($updateWorkers as $workerVersion) {
                    $this->runWorker($workerVersion);
                }
                $transaction->commit();     
            } catch (Exception $e) {
                $transaction->rollBack();
                $notify->addError(Yii::t('update', 'The update has failed!!!'));
                $notify->addError($e->getMessage());
            }
            
            $db->createCommand('SET SQL_MODE=@OLD_SQL_MODE')->execute();
            $db->createCommand('SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS')->execute();
            $db->createCommand('SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS')->execute();
            
            if ($notify->hasError) {
                $this->redirect(array('update/index'));
            }
            
            // clean directories of old asset files.
            $cleanDirectories = array(
                Yii::getPathOfAlias('common.runtime.cache'),
                Yii::getPathOfAlias('root.backend.assets.cache'),
                Yii::getPathOfAlias('root.customer.assets.cache'),
                Yii::getPathOfAlias('root.frontend.assets.cache'),
            );
            
            foreach ($cleanDirectories as $directory) {
                if (file_exists($directory) && is_dir($directory)) {
                    FileSystemHelper::deleteDirectoryContents($directory, true, 0);
                }
            }
            
            $options->set('system.common.version', $versionInFile);
            $options->set('system.common.site_status', 'online');
            $options->set('system.common.version_update.current_version', $versionInFile);
            
            $notify->addSuccess(Yii::t('update', 'Congratulations, your application has been successfully updated to version {version}', array(
                '{version}' => '<span class="badge">'.$versionInFile.'</span>',
            )));

            $this->redirect(array('dashboard/index'));
        }

        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | ' . Yii::t('update', 'Update'), 
            'pageHeading'       => Yii::t('update', 'Application update'),
            'pageBreadcrumbs'   => array(
                Yii::t('update', 'Update'),
            ),
        ));

        $this->render('index', compact('versionInFile', 'versionInDb'));
    }
    
    protected function runWorker($version)
    {
        $workersPath    = Yii::getPathOfAlias('backend.components.update');
        $version        = str_replace('.', '_', $version);
        $className      = 'UpdateWorkerFor_' . $version;
        
        if (!is_file($classFile = $workersPath . '/' . $className . '.php')) {
            return false;
        }
        
        require_once $classFile;
        $instance = new $className();
        
        if ($instance instanceof UpdateWorkerAbstract) {
            $instance->run();
        }
        
        return true;
    }
    
}