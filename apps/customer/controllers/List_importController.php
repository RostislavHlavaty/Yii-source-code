<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * List_importController
 * 
 * Handles the actions for list import related tasks
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
class List_importController extends Controller
{
    public function init()
    {
        parent::init();
                
        if (Yii::app()->options->get('system.importer.enabled', 'yes') != 'yes') {
            $this->redirect(array('lists/index'));
        }
        
        $customer = Yii::app()->customer->getModel();
        if ($customer->getGroupOption('lists.can_import_subscribers', 'yes') != 'yes') {
            $this->redirect(array('lists/index'));
        }
        
        $this->getData('pageScripts')->add(array('src' => AssetsUrl::js('list-import.js')));
        
    }
    
    /**
     * Define the filters for various controller actions
     * Merge the filters with the ones from parent implementation
     */
    public function filters()
    {
        return CMap::mergeArray(parent::filters(), array(
            'postOnly + csv, database', 
        ));
    }
    
    /**
     * List available import options
     */
    public function actionIndex($list_uid)
    {
        $list       = $this->loadListModel($list_uid);
        $options    = Yii::app()->options;
        $request    = Yii::app()->request;
        $importCsv  = new ListCsvImport();
        $importDb   = new ListDatabaseImport();
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('list_import', 'Import subscribers into your list'), 
            'pageHeading'       => Yii::t('list_import', 'Import subscribers'), 
            'pageBreadcrumbs'   => array(
                Yii::t('lists', 'Lists') => $this->createUrl('lists/index'), 
                $list->name => $this->createUrl('lists/overview', array('list_uid' => $list->list_uid)),
                Yii::t('list_import', 'Import subscribers')
            )
        ));
        
        $maxUploadSize = (int)$options->get('system.importer.file_size_limit', 1024 * 1024 * 1) / 1024 / 1024;
        $this->render('list', compact('list', 'importCsv', 'maxUploadSize', 'importDb'));
    }
    
    /**
     * Handle the CSV import
     */
    public function actionCsv($list_uid)
    {
        $list       = $this->loadListModel($list_uid);
        $options    = Yii::app()->options;
        $request    = Yii::app()->request;
        $notify     = Yii::app()->notify;
        
        $importLog = array();
        $filePath = Yii::getPathOfAlias('common.runtime.list-import').'/';
        
        $importAtOnce = (int)$options->get('system.importer.import_at_once', 50);
        $pause = (int)$options->get('system.importer.pause', 1);
        
        set_time_limit(0);
        if ($memoryLimit = $options->get('system.importer.memory_limit')) {
            ini_set('memory_limit', $memoryLimit);    
        }
        ini_set('auto_detect_line_endings', true);
        
        $import = new ListCsvImport();
        $import->file_size_limit = (int)$options->get('system.importer.file_size_limit', 1024 * 1024 * 1); // 1 mb
        $import->file = CUploadedFile::getInstance($import, 'file');
        $import->attributes = (array)$request->getPost($import->modelName, array());
        
        if (!empty($import->file)) {
            if (!$import->upload()) {
                $notify->addError(Yii::t('app', 'Your form has a few errors, please fix them and try again!'));
                $notify->addError($import->shortErrors->getAllAsString());
                $this->redirect(array('list_import/index', 'list_uid' => $list->list_uid));
            }
            
            $this->setData(array(
                'pageMetaTitle'     => $this->data->pageMetaTitle.' | '.Yii::t('list_import', 'Import subscribers'), 
                'pageHeading'       => Yii::t('list_import', 'Import subscribers'), 
                'pageBreadcrumbs'   => array(
                    Yii::t('lists', 'Lists') => $this->createUrl('lists/index'), 
                    $list->name => $this->createUrl('lists/overview', array('list_uid' => $list->list_uid)),
                    Yii::t('list_import', 'CSV Import')
                )
            ));
            
            return $this->render('csv', compact('list', 'import', 'importAtOnce', 'pause'));
        }
        
        // only ajax from now on.
        if (!$request->isAjaxRequest) {
            $this->redirect(array('list_import/index', 'list_uid' => $list->list_uid));
        }

        try {
        
            if (!is_file($filePath.$import->file_name)) {
                return $this->renderJson(array(
                    'result'    => 'error', 
                    'message'   => Yii::t('list_import', 'The import file does not exist anymore!')
                ));
            }
            
            $file = new SplFileObject($filePath.$import->file_name);
            $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE | SplFileObject::READ_AHEAD);
            $columns = $file->current(); // the header

            if (empty($columns)) {
                unset($file);
                @unlink($filePath.$import->file_name);
                return $this->renderJson(array(
                    'result'    => 'error', 
                    'message'   => Yii::t('list_import', 'Your file does not contain the header with the fields title!')
                ));
            }
            
            if ($import->is_first_batch) {
                /*
                $linesCount = 0;
                while (!$file->eof()) {
                    $linesCount++;
                    $file->next();
                }
                */
                $linesCount = iterator_count($file);
                $totalFileRecords   = $linesCount - 1; // minus the header
                $import->rows_count = $totalFileRecords;    
            } else {
                $totalFileRecords = $import->rows_count;
            }

            $file->seek(1);
            
            $customer                = $list->customer;
            $totalSubscribersCount   = 0;
            $listSubscribersCount    = 0;
            $maxSubscribersPerList   = (int)$customer->getGroupOption('lists.max_subscribers_per_list', -1);
            $maxSubscribers          = (int)$customer->getGroupOption('lists.max_subscribers', -1);
            
            if ($maxSubscribers > -1 || $maxSubscribersPerList > -1) {
                $criteria = new CDbCriteria();
                $criteria->select = 't.email';
                $criteria->group  = 't.email';
                $criteria->with = array(
                    'list' => array(
                        'select'   => false,
                        'together' => true,
                        'joinType' => 'INNER JOIN',
                        'condition'=> 'list.customer_id = :cid',
                        'params'   => array(':cid' => (int)$customer->customer_id),
                    ),
                );
                
                if ($maxSubscribers > -1) {
                    $totalSubscribersCount = ListSubscriber::model()->count($criteria);
                    if ($totalSubscribersCount >= $maxSubscribers) {
                        return $this->renderJson(array(
                            'result'  => 'error',
                            'message' => Yii::t('lists', 'You have reached the maximum number of allowed subscribers.'),
                        ));
                    }    
                }
                
                if ($maxSubscribersPerList > -1) {
                    $criteria->compare('list.list_id', (int)$list->list_id);
                    $listSubscribersCount = ListSubscriber::model()->count($criteria);
                    if ($listSubscribersCount >= $maxSubscribersPerList) {
                        return $this->renderJson(array(
                            'result'  => 'error',
                            'message' => Yii::t('lists', 'You have reached the maximum number of allowed subscribers into this list.'),
                        ));
                    }
                }
            }
            
            
            $criteria = new CDbCriteria();
            $criteria->select = 'field_id, label, tag';
            $criteria->compare('list_id', $list->list_id);
            $fields = ListField::model()->findAll($criteria);
            
            $foundTags = array();
            $searchReplaceTags = array(
                'E_MAIL'        => 'EMAIL',
                'EMAIL_ADDRESS' => 'EMAIL',
                'EMAILADDRESS'  => 'EMAIL',
            );
            foreach ($fields as $field) {
                if ($field->tag == 'FNAME') {
                    $searchReplaceTags['F_NAME']        = 'FNAME';
                    $searchReplaceTags['FIRST_NAME']    = 'FNAME';
                    $searchReplaceTags['FIRSTNAME']     = 'FNAME';
                    continue;
                }
                if ($field->tag == 'LNAME') {
                    $searchReplaceTags['L_NAME']    = 'LNAME';
                    $searchReplaceTags['LAST_NAME'] = 'LNAME';
                    $searchReplaceTags['LASTNAME']  = 'LNAME';
                    continue;
                }
            }
            
            $ioFilter = Yii::app()->ioFilter;
            $columns = $ioFilter->stripTags($ioFilter->xssClean($columns));
            
            foreach ($columns as $value) {
                $tagName = StringHelper::getTagFromString($value);
                $tagName = str_replace(array_keys($searchReplaceTags), array_values($searchReplaceTags), $tagName);
                $foundTags[] = $tagName;
            }
            
            $foundEmailTag = false;
            foreach ($foundTags as $tagName) {
                if ($tagName === 'EMAIL') {
                    $foundEmailTag = true;
                    break;
                }
            }
            
            if (!$foundEmailTag) {
                unset($file);
                @unlink($filePath.$import->file_name);
                return $this->renderJson(array(
                    'result'    => 'error', 
                    'message'   => Yii::t('list_import', 'Cannot find the "email" column in your file!')
                ));
            }
            
            $foundReservedColumns = array();
            foreach ($columns as $columnName) {
                $columnName = StringHelper::getTagFromString($columnName);
                $columnName = str_replace(array_keys($searchReplaceTags), array_values($searchReplaceTags), $columnName);
                $tagIsReserved = TagRegistry::model()->findByAttributes(array('tag' => '['.$columnName.']'));
                if (!empty($tagIsReserved)) {
                    $foundReservedColumns[] = $columnName;
                }
            }
            
            if (!empty($foundReservedColumns)) {
                unset($file);
                @unlink($filePath.$import->file_name);
                return $this->renderJson(array(
                    'result'    => 'error', 
                    'message'   => Yii::t('list_import', 'Your list contains the columns: "{columns}" which are system reserved. Please update your file and change the column names!', array(
                        '{columns}' => implode(', ', $foundReservedColumns)
                    ))
                ));    
            }

            if ($import->is_first_batch) {
                if ($logAction = Yii::app()->customer->getModel()->asa('logAction')) {
                    $logAction->listImportStart($list, $import);    
                }
    
                $importLog[] = array(
                    'type'      => 'info',
                    'message'   => Yii::t('list_import', 'Found the following column names: {columns}', array(
                        '{columns}' => implode(', ', $columns)
                    )),
                    'counter'   => false,
                );
            }
            
            $offset = $importAtOnce * ($import->current_page - 1);
            if ($offset >= $totalFileRecords) {
                return $this->renderJson(array(
                    'result'    => 'success', 
                    'message'   => Yii::t('list_import', 'The import process has finished!')
                ));
            }
            $file->seek($offset);

            $csvData = array();
            $columnCount = count($columns);
            $i = 0;
            
            while (!$file->eof()) {
                
                $row = $file->fgetcsv();
                if (empty($row)) {
                    continue;
                }
                
                $row = $ioFilter->stripTags($ioFilter->xssClean($row));
                $rowCount = count($row);
                
                if ($rowCount == 0) {
                    continue;
                }

                $isEmpty = true;
                foreach ($row as $value) {
                    if (!empty($value)) {
                        $isEmpty = false;
                        break;
                    }
                }
                
                if ($isEmpty) {
                    continue;
                }
                
                if ($columnCount > $rowCount) {
                    $fill = array_fill($rowCount, $columnCount - $rowCount, '');
                    $row = array_merge($row, $fill);
                } elseif ($rowCount > $columnCount) {
                    $row = array_slice($row, 0, $columnCount);
                }
                
                $csvData[] = array_combine($columns, $row);
                
                ++$i;
                
                if ($i >= $importAtOnce) {
                    break;
                }
            }
            unset($file);
            
            $fieldType = ListFieldType::model()->findByAttributes(array(
                'identifier' => 'text', 
            ));
            
            $data = array();
            foreach ($csvData as $row) {
                $rowData = array();
                foreach ($row as $name => $value) {
                    $tagName = StringHelper::getTagFromString($name);
                    $tagName = str_replace(array_keys($searchReplaceTags), array_values($searchReplaceTags), $tagName);

                    $rowData[] = array(
                        'name'      => ucwords(str_replace('_', ' ', $name)),
                        'tagName'   => trim($tagName),
                        'tagValue'  => trim($value),
                    );
                }
                $data[] = $rowData;
            }
            unset($csvData);
            
            if (empty($data) || count($data) < 1) {
                @unlink($filePath.$import->file_name);
                
                if ($logAction = Yii::app()->customer->getModel()->asa('logAction')) {
                    $logAction->listImportEnd($list, $import);    
                }
                
                if ($import->is_first_batch) {
                    return $this->renderJson(array(
                        'result'    => 'error', 
                        'message'   => Yii::t('list_import', 'Your file does not contain enough data to be imported!')
                    ));    
                } else {
                    return $this->renderJson(array(
                        'result'    => 'success', 
                        'message'   => Yii::t('list_import', 'The import process has finished!')
                    ));    
                }
            }
            
            $tagToModel = array();
            foreach ($data[0] as $sample) {
            
                if ($import->is_first_batch) {
                    $importLog[] = array(
                        'type'      => 'info', 
                        'message'   => Yii::t('list_import', 'Checking to see if the tag "{tag}" is defined in your list fields...', array(
                            '{tag}' => CHtml::encode($sample['tagName'])
                        )),
                        'counter'   => false,
                    );
                }
                
                $model = ListField::model()->findByAttributes(array(
                    'list_id'   => $list->list_id, 
                    'tag'       => $sample['tagName']
                ));
                
                if (!empty($model)) {
                
                    if ($import->is_first_batch) {
                        $importLog[] = array(
                            'type'      => 'info',
                            'message'   => Yii::t('list_import', 'The tag "{tag}" is already defined in your list fields.', array(
                                '{tag}' => CHtml::encode($sample['tagName'])
                            )),
                            'counter'   => false,
                        );
                    }
                    
                    $tagToModel[$sample['tagName']] = $model;
                    continue;
                }
                
                if ($import->is_first_batch) {
                    $importLog[] = array(
                        'type'      => 'info',
                        'message'   => Yii::t('list_import', 'The tag "{tag}" is not defined in your list fields, we will try to create it.', array(
                            '{tag}' => CHtml::encode($sample['tagName'])
                        )),
                        'counter'   => false,
                    );
                }
                
                $model = new ListField();
                $model->type_id = $fieldType->type_id;
                $model->list_id = $list->list_id;
                $model->label   = $sample['name'];
                $model->tag     = $sample['tagName'];
                
                if ($model->save(false)) {
                
                    if ($import->is_first_batch) {
                        $importLog[] = array(
                            'type'      => 'success',
                            'message'   => Yii::t('list_import', 'The tag "{tag}" has been successfully created.', array(
                                '{tag}' => CHtml::encode($sample['tagName'])
                            )),
                            'counter'   => false,
                        );
                    }
                    
                    $tagToModel[$sample['tagName']] = $model;
                
                } else {
                
                    if ($import->is_first_batch) {
                        $importLog[] = array(
                            'type'      => 'error',
                            'message'   => Yii::t('list_import', 'The tag "{tag}" cannot be saved, reason: {reason}', array(
                                '{tag}' => CHtml::encode($sample['tagName']), 
                                '{reason}' => '<br />'.$model->shortErrors->getAllAsString()
                            )),
                            'counter'   => false,
                        );
                    }
                }
            }
            
            $finished    = false;
            $importCount = 0;
            $transaction = Yii::app()->getDb()->beginTransaction();
            
            try {
                
                foreach ($data as $index => $fields) {
                    
                    $email = null;
                    foreach ($fields as $detail) {
                        if ($detail['tagName'] == 'EMAIL' && !empty($detail['tagValue'])) {
                            $email = $detail['tagValue'];
                            break;
                        }
                    }
                    
                    if (empty($email)) {
                        unset($data[$index]);
                        continue;
                    }
                    
                    $importLog[] = array(
                        'type'      => 'info', 
                        'message'   => Yii::t('list_import', 'Checking the list for the email: "{email}"', array(
                            '{email}' => CHtml::encode($email), 
                        )),
                        'counter'   => false,
                    );
                    
                    $subscriber = ListSubscriber::model()->findByAttributes(array(
                        'list_id'   => $list->list_id,
                        'email'     => $email,
                    ));
                    
                    if (empty($subscriber)) {
                        
                        $importLog[] = array(
                            'type'      => 'info', 
                            'message'   => Yii::t('list_import', 'The email "{email}" was not found, we will try to create it...', array(
                                '{email}' => CHtml::encode($email), 
                            )), 
                            'counter'   => false,
                        );
                        
                        $subscriber = new ListSubscriber();
                        $subscriber->list_id    = $list->list_id;
                        $subscriber->email      = $email;
                        $subscriber->source     = ListSubscriber::SOURCE_IMPORT;
                        $subscriber->status     = ListSubscriber::STATUS_CONFIRMED;
                        
                        $validator = new CEmailValidator();
                        if (Yii::app()->options->get('system.common.dns_email_check', false)) {
                            $validator->checkMX     = CommonHelper::functionExists('checkdnsrr');
                            $validator->checkPort   = CommonHelper::functionExists('dns_get_record') && CommonHelper::functionExists('fsockopen');    
                        }
                        $validEmail = $validator->validateValue($email);
                        
                        if (!$validEmail) {
                            $subscriber->addError('email', Yii::t('list_import', 'Invalid email address!'));
                        } else {
                            $blacklisted = EmailBlacklist::isBlacklisted($email);
                            if (!empty($blacklisted)) {
                                $subscriber->addError('email', Yii::t('list_import', 'This email address is blacklisted!'));
                            }
                        }
                        
                        if (!$validEmail || $subscriber->hasErrors() || !$subscriber->save()) {
                            $importLog[] = array(
                                'type'      => 'error', 
                                'message'   => Yii::t('list_import', 'Failed to save the email "{email}", reason: {reason}', array(
                                    '{email}'   => CHtml::encode($email), 
                                    '{reason}'  => '<br />'.$subscriber->shortErrors->getAllAsString()
                                )),
                                'counter'   => true,
                            );
                            continue;
                        }

                        $listSubscribersCount++;
                        $totalSubscribersCount++;
                        
                        if ($maxSubscribersPerList > -1 && $listSubscribersCount >= $maxSubscribersPerList) {
                            $finished = Yii::t('lists', 'You have reached the maximum number of allowed subscribers into this list.');
                            break;
                        }
                        
                        if ($maxSubscribers > -1 && $totalSubscribersCount >= $maxSubscribers) {
                            $finished = Yii::t('lists', 'You have reached the maximum number of allowed subscribers.');
                            break;
                        }
                        
                        $importLog[] = array(
                            'type'      => 'success', 
                            'message'   => Yii::t('list_import', 'The email "{email}" has been successfully saved.', array(
                                '{email}' => CHtml::encode($email), 
                            )),
                            'counter'   => true,
                        );
                    
                    } else {
                        
                        $importLog[] = array(
                            'type'      => 'info', 
                            'message'   => Yii::t('list_import', 'The email "{email}" has been found, we will update it.', array(
                                '{email}' => CHtml::encode($email), 
                            )),
                            'counter'   => true,
                        );
                    }
                    
                    foreach ($fields as $detail) {
                        if (!isset($tagToModel[$detail['tagName']])) {
                            continue;
                        }
                        $fieldModel = $tagToModel[$detail['tagName']];
                        $valueModel = ListFieldValue::model()->findByAttributes(array(
                            'field_id'      => $fieldModel->field_id,
                            'subscriber_id' => $subscriber->subscriber_id,
                        ));
                        if (empty($valueModel)) {
                            $valueModel = new ListFieldValue();
                            $valueModel->field_id = $fieldModel->field_id;
                            $valueModel->subscriber_id = $subscriber->subscriber_id;
                        }
                        $valueModel->value = $detail['tagValue'];
                        $valueModel->save();
                    }
                    
                    unset($data[$index]);
                    ++$importCount;
                    
                    if ($finished) {
                        break;
                    }
                }  
                
                $transaction->commit();  
            
            } catch(Exception $e) {
                
                if (isset($file)) {
                    unset($file);
                }
                
                if (is_file($filePath.$import->file_name)) {
                    @unlink($filePath.$import->file_name);
                }
                
                $transaction->rollback();
                return $this->renderJson(array(
                    'result'    => 'error', 
                    'message'   => $e->getMessage(),
                ));
            }
            
            if ($finished) {
                return $this->renderJson(array(
                    'result'    => 'error', 
                    'message'   => $finished,
                ));
            }

            $import->is_first_batch = 0;
            $import->current_page++;
            
            return $this->renderJson(array(
                'result'    => 'success',
                'message'   => Yii::t('list_import', 'Imported {count} subscribers starting from row {rowStart} and ending with row {rowEnd}! Going further, please wait...', array(
                    '{count}'       => $importCount,
                    '{rowStart}'    => $offset,
                    '{rowEnd}'      => $offset + $importAtOnce,
                )),
                'attributes'    => $import->attributes,
                'import_log'    => $importLog,
                'recordsCount'  => $totalFileRecords,
            ));            
        
        } catch(Exception $e) {
            
            if (isset($file)) {
                unset($file);
            }
            
            if (is_file($filePath.$import->file_name)) {
                @unlink($filePath.$import->file_name);
            }
            
            return $this->renderJson(array(
                'result'    => 'error', 
                'message'   => Yii::t('list_import', 'Your file cannot be imported, a general error has been encountered: {message}!', array(
                    '{message}' => $e->getMessage()
                ))
            ));
            
        }
    }
    
    /**
     * Handle the Database import
     */
    public function actionDatabase($list_uid)
    {
        $list     = $this->loadListModel($list_uid);
        $options  = Yii::app()->options;
        $request  = Yii::app()->request;
        $notify   = Yii::app()->notify;
        
        if (!$request->isPostRequest) {
            $this->redirect(array('list_import/index', 'list_uid' => $list->list_uid));
        }
        
        $importAtOnce = (int)$options->get('system.importer.import_at_once', 50);
        $pause        = (int)$options->get('system.importer.pause', 1);
        
        set_time_limit(0);
        if ($memoryLimit = $options->get('system.importer.memory_limit')) {
            ini_set('memory_limit', $memoryLimit);    
        }

        $import = new ListDatabaseImport();
        $import->attributes = (array)$request->getPost($import->modelName, array());
        $import->validateAndConnect();
        
        if ($import->hasErrors()) {
            $message = Yii::t('app', 'Your form has a few errors, please fix them and try again!') . '<br />' . $import->shortErrors->getAllAsString();
            if ($request->isAjaxRequest) {
               return $this->renderJson(array(
                    'result'    => 'error', 
                    'message'   => $message
                )); 
            }
            $notify->addError($message);
            $this->redirect(array('list_import/index', 'list_uid' => $list->list_uid));
        }
        
        if (!$request->isAjaxRequest) {
            
            $this->setData(array(
                'pageMetaTitle'     => $this->data->pageMetaTitle.' | '.Yii::t('list_import', 'Import subscribers'), 
                'pageHeading'       => Yii::t('list_import', 'Import subscribers'), 
                'pageBreadcrumbs'   => array(
                    Yii::t('lists', 'Lists') => $this->createUrl('lists/index'), 
                    $list->name => $this->createUrl('lists/overview', array('list_uid' => $list->list_uid)),
                    Yii::t('list_import', 'Database Import')
                )
            ));
            
            return $this->render('database', compact('list', 'import', 'importAtOnce', 'pause'));
        }

        try {
            
            $columns = $import->getColumns();
            if (empty($columns)) {
                return $this->renderJson(array(
                    'result'    => 'error', 
                    'message'   => Yii::t('list_import', 'Cannot find your database columns!')
                ));
            }
            
            if ($import->is_first_batch) {
                $totalRecords = $import->rows_count = $import->countResults();    
            } else {
                $totalRecords = $import->rows_count;
            }
            
            $customer                = $list->customer;
            $totalSubscribersCount   = 0;
            $listSubscribersCount    = 0;
            $maxSubscribersPerList   = (int)$customer->getGroupOption('lists.max_subscribers_per_list', -1);
            $maxSubscribers          = (int)$customer->getGroupOption('lists.max_subscribers', -1);
            
            if ($maxSubscribers > -1 || $maxSubscribersPerList > -1) {
                $criteria = new CDbCriteria();
                $criteria->select = 't.email';
                $criteria->group  = 't.email';
                $criteria->with = array(
                    'list' => array(
                        'select'   => false,
                        'together' => true,
                        'joinType' => 'INNER JOIN',
                        'condition'=> 'list.customer_id = :cid',
                        'params'   => array(':cid' => (int)$customer->customer_id),
                    ),
                );
                
                if ($maxSubscribers > -1) {
                    $totalSubscribersCount = ListSubscriber::model()->count($criteria);
                    if ($totalSubscribersCount >= $maxSubscribers) {
                        return $this->renderJson(array(
                            'result'  => 'error',
                            'message' => Yii::t('lists', 'You have reached the maximum number of allowed subscribers.'),
                        ));
                    }    
                }
                
                if ($maxSubscribersPerList > -1) {
                    $criteria->compare('list.list_id', (int)$list->list_id);
                    $listSubscribersCount = ListSubscriber::model()->count($criteria);
                    if ($listSubscribersCount >= $maxSubscribersPerList) {
                        return $this->renderJson(array(
                            'result'  => 'error',
                            'message' => Yii::t('lists', 'You have reached the maximum number of allowed subscribers into this list.'),
                        ));
                    }
                }
            }

            $criteria = new CDbCriteria();
            $criteria->select = 'field_id, label, tag';
            $criteria->compare('list_id', $list->list_id);
            $fields = ListField::model()->findAll($criteria);
            
            $foundTags = array();
            $searchReplaceTags = array(
                'E_MAIL'        => 'EMAIL',
                'EMAIL_ADDRESS' => 'EMAIL',
                'EMAILADDRESS'  => 'EMAIL',
            );
            foreach ($fields as $field) {
                if ($field->tag == 'FNAME') {
                    $searchReplaceTags['F_NAME']        = 'FNAME';
                    $searchReplaceTags['FIRST_NAME']    = 'FNAME';
                    $searchReplaceTags['FIRSTNAME']     = 'FNAME';
                    continue;
                }
                if ($field->tag == 'LNAME') {
                    $searchReplaceTags['L_NAME']    = 'LNAME';
                    $searchReplaceTags['LAST_NAME'] = 'LNAME';
                    $searchReplaceTags['LASTNAME']  = 'LNAME';
                    continue;
                }
            }

            foreach ($columns as $value) {
                $tagName = StringHelper::getTagFromString($value);
                $tagName = str_replace(array_keys($searchReplaceTags), array_values($searchReplaceTags), $tagName);
                $foundTags[] = $tagName;
            }
            
            $foundEmailTag = false;
            foreach ($foundTags as $tagName) {
                if ($tagName === 'EMAIL') {
                    $foundEmailTag = true;
                    break;
                }
            }

            if (!$foundEmailTag) {
                return $this->renderJson(array(
                    'result'    => 'error', 
                    'message'   => Yii::t('list_import', 'Cannot find the "email" column in your database!')
                ));
            }
            
            $foundReservedColumns = array();
            foreach ($columns as $columnName) {
                $columnName = StringHelper::getTagFromString($columnName);
                $columnName = str_replace(array_keys($searchReplaceTags), array_values($searchReplaceTags), $columnName);
                $tagIsReserved = TagRegistry::model()->findByAttributes(array('tag' => '['.$columnName.']'));
                if (!empty($tagIsReserved)) {
                    $foundReservedColumns[] = $columnName;
                }
            }
            
            if (!empty($foundReservedColumns)) {
                return $this->renderJson(array(
                    'result'    => 'error', 
                    'message'   => Yii::t('list_import', 'Your database contains the columns: "{columns}" which are system reserved. Please update your database and change the column names or ignore them!', array(
                        '{columns}' => implode(', ', $foundReservedColumns)
                    ))
                ));    
            }

            if ($import->is_first_batch) {
                if ($logAction = Yii::app()->customer->getModel()->asa('logAction')) {
                    $logAction->listImportStart($list, $import);    
                }
    
                $importLog[] = array(
                    'type'      => 'info',
                    'message'   => Yii::t('list_import', 'Found the following column names: {columns}', array(
                        '{columns}' => implode(', ', $columns)
                    )),
                    'counter'   => false,
                );
            }
            
            $offset = $importAtOnce * ($import->current_page - 1);
            if ($offset >= $totalRecords) {
                if ($logAction = Yii::app()->customer->getModel()->asa('logAction')) {
                    $logAction->listImportEnd($list, $import);    
                }
                return $this->renderJson(array(
                    'result'    => 'success', 
                    'message'   => Yii::t('list_import', 'The import process has finished!')
                ));
            }
            
            $results = $import->getResults($offset, $importAtOnce);
            if (empty($results)) {
                if ($logAction = Yii::app()->customer->getModel()->asa('logAction')) {
                    $logAction->listImportEnd($list, $import);    
                }
                return $this->renderJson(array(
                    'result'    => 'success', 
                    'message'   => Yii::t('list_import', 'The import process has finished!')
                ));
            }
            
            $fieldType = ListFieldType::model()->findByAttributes(array(
                'identifier' => 'text', 
            ));
            
            $data = array();
            foreach ($results as $result) {
                $rowData = array();
                foreach ($result as $name => $value) {
                    $tagName = StringHelper::getTagFromString($name);
                    $tagName = str_replace(array_keys($searchReplaceTags), array_values($searchReplaceTags), $tagName);
    
                    $rowData[] = array(
                        'name'      => ucwords(str_replace('_', ' ', $name)),
                        'tagName'   => trim($tagName),
                        'tagValue'  => trim($value),
                    );
                }
                $data[] = $rowData;
            }

            if (empty($data) || count($data) < 1) {

                if ($logAction = Yii::app()->customer->getModel()->asa('logAction')) {
                    $logAction->listImportEnd($list, $import);    
                }
                
                if ($import->is_first_batch) {
                    return $this->renderJson(array(
                        'result'    => 'error', 
                        'message'   => Yii::t('list_import', 'Your database does not contain enough data to be imported!')
                    ));    
                } else {
                    return $this->renderJson(array(
                        'result'    => 'success', 
                        'message'   => Yii::t('list_import', 'The import process has finished!')
                    ));    
                }
            }
            
            $tagToModel = array();
            foreach ($data[0] as $sample) {
            
                if ($import->is_first_batch) {
                    $importLog[] = array(
                        'type'      => 'info', 
                        'message'   => Yii::t('list_import', 'Checking to see if the tag "{tag}" is defined in your list fields...', array(
                            '{tag}' => CHtml::encode($sample['tagName'])
                        )),
                        'counter'   => false,
                    );
                }
                
                $model = ListField::model()->findByAttributes(array(
                    'list_id'   => $list->list_id, 
                    'tag'       => $sample['tagName']
                ));
                
                if (!empty($model)) {
                
                    if ($import->is_first_batch) {
                        $importLog[] = array(
                            'type'      => 'info',
                            'message'   => Yii::t('list_import', 'The tag "{tag}" is already defined in your list fields.', array(
                                '{tag}' => CHtml::encode($sample['tagName'])
                            )),
                            'counter'   => false,
                        );
                    }
                    
                    $tagToModel[$sample['tagName']] = $model;
                    continue;
                }
                
                if ($import->is_first_batch) {
                    $importLog[] = array(
                        'type'      => 'info',
                        'message'   => Yii::t('list_import', 'The tag "{tag}" is not defined in your list fields, we will try to create it.', array(
                            '{tag}' => CHtml::encode($sample['tagName'])
                        )),
                        'counter'   => false,
                    );
                }
                
                $model = new ListField();
                $model->type_id = $fieldType->type_id;
                $model->list_id = $list->list_id;
                $model->label   = $sample['name'];
                $model->tag     = $sample['tagName'];
                
                if ($model->save(false)) {
                
                    if ($import->is_first_batch) {
                        $importLog[] = array(
                            'type'      => 'success',
                            'message'   => Yii::t('list_import', 'The tag "{tag}" has been successfully created.', array(
                                '{tag}' => CHtml::encode($sample['tagName'])
                            )),
                            'counter'   => false,
                        );
                    }
                    
                    $tagToModel[$sample['tagName']] = $model;
                
                } else {
                
                    if ($import->is_first_batch) {
                        $importLog[] = array(
                            'type'      => 'error',
                            'message'   => Yii::t('list_import', 'The tag "{tag}" cannot be saved, reason: {reason}', array(
                                '{tag}' => CHtml::encode($sample['tagName']), 
                                '{reason}' => '<br />'.$model->shortErrors->getAllAsString()
                            )),
                            'counter'   => false,
                        );
                    }
                }
            }
            
            $finished    = false;
            $importCount = 0;
            $transaction = Yii::app()->getDb()->beginTransaction();
            
            try {
                
                foreach ($data as $index => $fields) {
                    
                    $email = null;
                    foreach ($fields as $detail) {
                        if ($detail['tagName'] == 'EMAIL' && !empty($detail['tagValue'])) {
                            $email = $detail['tagValue'];
                            break;
                        }
                    }
                    
                    if (empty($email)) {
                        unset($data[$index]);
                        continue;
                    }
                    
                    $importLog[] = array(
                        'type'      => 'info', 
                        'message'   => Yii::t('list_import', 'Checking the list for the email: "{email}"', array(
                            '{email}' => CHtml::encode($email), 
                        )),
                        'counter'   => false,
                    );
                    
                    $subscriber = ListSubscriber::model()->findByAttributes(array(
                        'list_id'   => $list->list_id,
                        'email'     => $email,
                    ));
                    
                    if (empty($subscriber)) {
                        
                        $importLog[] = array(
                            'type'      => 'info', 
                            'message'   => Yii::t('list_import', 'The email "{email}" was not found, we will try to create it...', array(
                                '{email}' => CHtml::encode($email), 
                            )), 
                            'counter'   => false,
                        );
                        
                        $subscriber = new ListSubscriber();
                        $subscriber->list_id    = $list->list_id;
                        $subscriber->email      = $email;
                        $subscriber->source     = ListSubscriber::SOURCE_IMPORT;
                        $subscriber->status     = ListSubscriber::STATUS_CONFIRMED;
                        
                        $validator = new CEmailValidator();
                        if (Yii::app()->options->get('system.common.dns_email_check', false)) {
                            $validator->checkMX     = CommonHelper::functionExists('checkdnsrr');
                            $validator->checkPort   = CommonHelper::functionExists('dns_get_record') && CommonHelper::functionExists('fsockopen');    
                        }
                        $validEmail = $validator->validateValue($email);
                        
                        if (!$validEmail) {
                            $subscriber->addError('email', Yii::t('list_import', 'Invalid email address!'));
                        } else {
                            $blacklisted = EmailBlacklist::isBlacklisted($email);
                            if (!empty($blacklisted)) {
                                $subscriber->addError('email', Yii::t('list_import', 'This email address is blacklisted!'));
                            }
                        }
                        
                        if (!$validEmail || $subscriber->hasErrors() || !$subscriber->save()) {
                            $importLog[] = array(
                                'type'      => 'error', 
                                'message'   => Yii::t('list_import', 'Failed to save the email "{email}", reason: {reason}', array(
                                    '{email}'   => CHtml::encode($email), 
                                    '{reason}'  => '<br />'.$subscriber->shortErrors->getAllAsString()
                                )),
                                'counter'   => true,
                            );
                            continue;
                        }

                        $listSubscribersCount++;
                        $totalSubscribersCount++;
                        
                        if ($maxSubscribersPerList > -1 && $listSubscribersCount >= $maxSubscribersPerList) {
                            $finished = Yii::t('lists', 'You have reached the maximum number of allowed subscribers into this list.');
                            break;
                        }
                        
                        if ($maxSubscribers > -1 && $totalSubscribersCount >= $maxSubscribers) {
                            $finished = Yii::t('lists', 'You have reached the maximum number of allowed subscribers.');
                            break;
                        }
                        
                        $importLog[] = array(
                            'type'      => 'success', 
                            'message'   => Yii::t('list_import', 'The email "{email}" has been successfully saved.', array(
                                '{email}' => CHtml::encode($email), 
                            )),
                            'counter'   => true,
                        );
                    
                    } else {
                        
                        $importLog[] = array(
                            'type'      => 'info', 
                            'message'   => Yii::t('list_import', 'The email "{email}" has been found, we will update it.', array(
                                '{email}' => CHtml::encode($email), 
                            )),
                            'counter'   => true,
                        );
                    }
                    
                    foreach ($fields as $detail) {
                        if (!isset($tagToModel[$detail['tagName']])) {
                            continue;
                        }
                        $fieldModel = $tagToModel[$detail['tagName']];
                        $valueModel = ListFieldValue::model()->findByAttributes(array(
                            'field_id'      => $fieldModel->field_id,
                            'subscriber_id' => $subscriber->subscriber_id,
                        ));
                        if (empty($valueModel)) {
                            $valueModel = new ListFieldValue();
                            $valueModel->field_id = $fieldModel->field_id;
                            $valueModel->subscriber_id = $subscriber->subscriber_id;
                        }
                        $valueModel->value = $detail['tagValue'];
                        $valueModel->save();
                    }
                    
                    unset($data[$index]);
                    ++$importCount;
                    
                    if ($finished) {
                        break;
                    }
                }  
                
                $transaction->commit();  
            
            } catch(Exception $e) {

                $transaction->rollback();
                return $this->renderJson(array(
                    'result'    => 'error', 
                    'message'   => $e->getMessage(),
                ));
            }
            
            if ($finished) {
                return $this->renderJson(array(
                    'result'    => 'error', 
                    'message'   => $finished,
                ));
            }

            $import->is_first_batch = 0;
            $import->current_page++;
            
            return $this->renderJson(array(
                'result'    => 'success',
                'message'   => Yii::t('list_import', 'Imported {count} subscribers starting from row {rowStart} and ending with row {rowEnd}! Going further, please wait...', array(
                    '{count}'       => $importCount,
                    '{rowStart}'    => $offset,
                    '{rowEnd}'      => $offset + $importAtOnce,
                )),
                'attributes'    => $import->attributes,
                'import_log'    => $importLog,
                'recordsCount'  => $totalRecords,
            )); 
            
        } catch (Exception $e) {
            
            return $this->renderJson(array(
                'result'    => 'error', 
                'message'   => Yii::t('list_import', 'Your database cannot be imported, a general error has been encountered: {message}!', array(
                    '{message}' => $e->getMessage()
                ))
            ));
            
        } 
    }
    
    /**
     * Will prevent the CSRF token expiration if the import takes too much time.
     */
    public function actionPing()
    {
        $this->render('ping');
    }
    
    /**
     * Helper method to load the list AR model
     */
    public function loadListModel($list_uid)
    {
        $model = Lists::model()->findByAttributes(array(
            'list_uid'      => $list_uid,
            'customer_id'   => (int)Yii::app()->customer->getId(),
        ));
        
        if ($model === null) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        return $model;
    }
}
