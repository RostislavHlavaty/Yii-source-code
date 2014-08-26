<?php defined('MW_PATH') || exit('No direct script access allowed');



/**

 * MiscController

 * 

 * Handles the actions for ip location services related tasks

 * 

 * @package MailWizz EMA

 * @author Serban George Cristian <cristian.serban@mailwizz.com> 

 * @link http://www.mailwizz.com/

 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)

 * @license http://www.mailwizz.com/license/

 * @since 1.3.3

 */

 

class MiscController extends Controller

{

    

    public function init()

    {

        $this->getData('pageScripts')->add(array('src' => AssetsUrl::js('misc.js')));

        parent::init();

    }

    

    public function actionIndex()

    {

        $this->redirect(array('misc/application_log'));

    }
	
	
	
    public function actionFilter_actions()
    {
	
	   $this->getData('pageStyles')->add(array('src' => AssetsUrl::css('ui.css'), 'priority' => 0));
	   $this->getData('pageStyles')->add(array('src' => AssetsUrl::css('jquery-ui.css'), 'priority' => 0));
	   $this->getData('pageStyles')->add(array('src' => AssetsUrl::css('style1.css'), 'priority' => 0));   
       $this->getData('pageScripts')->add(array('src' => AssetsUrl::js('func.js')));
	   $this->getData('pageScripts')->add(array('src' => AssetsUrl::js('datepicker.js')));
	   	   
	   $command = Yii::app()->db->createCommand();
			
			if(isset($_POST['export_domains'])){
						
						if(!empty($_FILES["file"]) && $_FILES["file"]["size"]!='0'){		
							$filename=$_FILES["file"]["tmp_name"];
							$file = fopen($filename, "r");
							Yii::app()->db->createCommand('TRUNCATE TABLE import');
							$loop = 1;
							while (($emapData = fgetcsv($file, 10000, ",")) !== FALSE){		
								
								if($loop>1){		
									$date = date("Y-m-d",strtotime($emapData['0']));
								    $command->insert('import', array('date_and_time' => "$date", 'ip_address' => "$emapData[1]", 'ip_address_label' => "$emapData[2]",  'browser' => "$emapData[3]", 'version' => "$emapData[4]", 'os' => "$emapData[5]", 'resolution' => "$emapData[6]", 'country' => "$emapData[7]", 'region' => "$emapData[8]", 'city' => "$emapData[9]", 'postal_code' => "$emapData[10]", 'isp' => "$emapData[11]", 'returning_count' => "$emapData[12]", 'page_url' => "$emapData[13]", 'page_title' => "$emapData[14]", 'came_from' => "[15]", 'se_name' => "$emapData[16]", 'se_host' => "$emapData[17]", 'se_term' => "$emapData[18]", 'type' => "$emapData[19]"));
							
								}	
								$loop++;		
							}
							
							$fromdate    = (isset($_POST['fromdate']))?trim($_POST['fromdate']):'';
							$todate      = (isset($_POST['todate']))?trim($_POST['todate']):'';
							$value_notin = (isset($_POST['value_notin']))?trim($_POST['value_notin']):'';
					
							$siteStr     = (isset($_POST['site']))?str_replace(' ','',$_POST['site']):'';
							$siteArray   = (isset($siteStr))?explode(',',$siteStr):array();
							
							$website = $sites = '';
							if(!empty($siteArray)){			
								foreach($siteArray as $values){
									$sites.= "'".$values."',";
								}
								$website = substr($sites,0,-1);
							}

							//echo "SELECT * FROM  `import` WHERE `page_url` IN (".$website.") AND `date_and_time` ".$value_notin." BETWEEN '".$fromdate."' AND '".$todate."'  ORDER BY ip_address DESC";

							//die;
							
							$comm = Yii::app()->db->createCommand("SELECT * FROM  `import` WHERE `page_url` ".$value_notin." IN (".$website.") AND `date_and_time` BETWEEN '".$fromdate."' AND '".$todate."'  ORDER BY ip_address DESC");
					        $data=$comm->queryAll();
							if(is_array($data) && !empty($data)){
								$date = date('d-m-Y');
								header('Cache-Control: no-cache');
								header('Content-Disposition: attachment; filename=glance-'.$date.'.csv');
								header("Content-Type: text/csv");
					
								$fp = fopen('php://memory', 'w');
								fputcsv($fp, array_keys($data[0]));
								foreach ($data as $fields) {
									fputcsv($fp, $fields);
								}
								rewind($fp);
								echo stream_get_contents($fp);
								fclose($fp);die;
							}			
						}
					}
					
					if(isset($_POST['export_ips'])){
						
						if(!empty($_FILES["file"]) && $_FILES["file"]["size"]!='0'){		
							$filename=$_FILES["file"]["tmp_name"];
							$file = fopen($filename, "r");
							Yii::app()->db->createCommand('TRUNCATE TABLE import');
							$loop = 1;
							while (($emapData = fgetcsv($file, 10000, ",")) !== FALSE){			
								if($loop>1){		
									$date = date("Y-m-d",strtotime($emapData['0']));
								    $command->insert('import', array('date_and_time' => "$date", 'ip_address' => "$emapData[1]", 'ip_address_label' => "$emapData[2]",  'browser' => "$emapData[3]", 'version' => "$emapData[4]", 'os' => "$emapData[5]", 'resolution' => "$emapData[6]", 'country' => "$emapData[7]", 'region' => "$emapData[8]", 'city' => "$emapData[9]", 'postal_code' => "$emapData[10]", 'isp' => "$emapData[11]", 'returning_count' => "$emapData[12]", 'page_url' => "$emapData[13]", 'page_title' => "$emapData[14]", 'came_from' => "[15]", 'se_name' => "$emapData[16]", 'se_host' => "$emapData[17]", 'se_term' => "$emapData[18]", 'type' => "$emapData[19]"));
							
								}	
								$loop++;		
							}	
							
							$fromdate    = (isset($_POST['ipfromdate']))?trim($_POST['ipfromdate']):'';
							$todate      = (isset($_POST['iptodate']))?trim($_POST['iptodate']):'';
							$value_notin = (isset($_POST['value_notin']))?trim($_POST['value_notin']):'';
					
							$ipsiteStr   = (isset($_POST['ipsite']))?str_replace(' ','',$_POST['ipsite']):'';
							$ipsiteArray = (isset($ipsiteStr))?explode(',',$ipsiteStr):array();
							
							$ips = $ip = '';
							if(!empty($ipsiteArray)){			
								foreach($ipsiteArray as $values){
									$ip.= "'".$values."',";
								}
								$ips = substr($ip,0,-1);
							}
					
							//echo "SELECT * FROM  `import` WHERE `ip_address` ".$value_notin." IN (".$ips.") AND `date_and_time` ".$value_notin." BETWEEN '".$fromdate."' AND '".$todate."' ORDER BY page_url DESC";die;

					        $comm = Yii::app()->db->createCommand("SELECT * FROM  `import` WHERE `ip_address` ".$value_notin." IN (".$ips.") AND `date_and_time` BETWEEN '".$fromdate."' AND '".$todate."' ORDER BY page_url DESC");
						    $data=$comm->queryAll();
						    if(is_array($data) && !empty($data)){
								$date = date('d-m-Y');
								header('Cache-Control: no-cache');
								header('Content-Disposition: attachment; filename=glance-'.$date.'.csv');
								header("Content-Type: text/csv");
					
								$fp = fopen('php://memory', 'w');
								fputcsv($fp, array_keys($data[0]));
								foreach ($data as $fields) {
									fputcsv($fp, $fields);
								}
								rewind($fp);
								echo stream_get_contents($fp);
								fclose($fp);die;
							}				
						}
					}
	   
        $this->render('filter-actions');

    }
	
	


	
	
	
	
    public function actionEmergency_actions()

    {

        $this->setData(array(

            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | ' . Yii::t('app', 'Emergency actions'), 

            'pageHeading'       => Yii::t('app', 'Emergency actions'),

            'pageBreadcrumbs'   => array(

                Yii::t('app', 'Emergency actions'),

            ),

        ));

        

        $this->render('emergency-actions');

    }

    

    public function actionRemove_sending_pid()

    {

        if (!Yii::app()->request->isAjaxRequest) {

            $this->redirect(array('misc/emergency_actions'));

        }

        Yii::app()->options->remove('system.cron.send_campaigns.lock');

        Yii::app()->options->set('system.cron.send_campaigns.campaigns_offset', 0);

        return $this->renderJson();

    }

    

    public function actionRemove_bounce_pid()

    {

        if (!Yii::app()->request->isAjaxRequest) {

            $this->redirect(array('misc/emergency_actions'));

        }

        Yii::app()->options->remove('system.cron.process_bounce_servers.pid');

        return $this->renderJson();

    }

    

    public function actionRemove_fbl_pid()

    {

        if (!Yii::app()->request->isAjaxRequest) {

            $this->redirect(array('misc/emergency_actions'));

        }

        Yii::app()->options->remove('system.cron.process_feedback_loop_servers.pid');

        return $this->renderJson();

    }

    

    public function actionReset_campaigns()

    {

        if (!Yii::app()->request->isAjaxRequest) {

            $this->redirect(array('misc/emergency_actions'));

        }

        Campaign::model()->updateAll(array('status' => Campaign::STATUS_SENDING), 'status = :status', array(':status' => Campaign::STATUS_PROCESSING));

        return $this->renderJson();

    }

    

    public function actionReset_bounce_servers()

    {

        if (!Yii::app()->request->isAjaxRequest) {

            $this->redirect(array('misc/emergency_actions'));

        }

        BounceServer::model()->updateAll(array('status' => BounceServer::STATUS_ACTIVE), 'status = :status', array(':status' => BounceServer::STATUS_CRON_RUNNING));

        return $this->renderJson();

    }

    

    public function actionReset_fbl_servers()

    {

        if (!Yii::app()->request->isAjaxRequest) {

            $this->redirect(array('misc/emergency_actions'));

        }

        FeedbackLoopServer::model()->updateAll(array('status' => FeedbackLoopServer::STATUS_ACTIVE), 'status = :status', array(':status' => FeedbackLoopServer::STATUS_CRON_RUNNING));

        return $this->renderJson();

    }

    

    public function actionApplication_log()

    {

        $request = Yii::app()->request;

        

        if ($request->isPostRequest && $request->getPost('delete') == 1) {

            if (is_file($file = Yii::app()->runtimePath . '/application.log')) {

                @unlink($file);

                Yii::app()->notify->addSuccess(Yii::t('app', 'The application log file has been successfully deleted!'));

            }

        }

        

        $this->setData(array(

            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | ' . Yii::t('app', 'Application log'), 

            'pageHeading'       => Yii::t('app', 'Application log'),

            'pageBreadcrumbs'   => array(

                Yii::t('app', 'Application log'),

            ),

        ));

        

        $applicationLog = FileSystemHelper::getFileContents(Yii::app()->runtimePath . '/application.log');

        $this->render('application-log', compact('applicationLog'));

    }



}