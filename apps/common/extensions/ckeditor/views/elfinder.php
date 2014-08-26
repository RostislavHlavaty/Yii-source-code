<?php defined('MW_PATH') || exit('No direct script access allowed');?>
<!DOCTYPE html>
<html>
	<head>
        <meta charset="<?php echo Yii::app()->charset;?>">
        <title><?php echo CHtml::encode($pageMetaTitle);?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="<?php echo CHtml::encode($pageMetaDescription);?>">
        
        <link rel="stylesheet" type="text/css" media="screen" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.18/themes/smoothness/jquery-ui.css">
        <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>
        <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.18/jquery-ui.min.js"></script>
        
        <link rel="stylesheet" type="text/css" media="screen" href="<?php echo $assetsUrl;?>/elfinder/css/elfinder.min.css">
        <link rel="stylesheet" type="text/css" media="screen" href="<?php echo $assetsUrl;?>/elfinder/css/theme.css">
        
        <script type="text/javascript" src="<?php echo $assetsUrl;?>/elfinder/js/elfinder.min.js"></script>
        <?php if ($language) { ?>
        <script type="text/javascript" src="<?php echo $assetsUrl;?>/elfinder/js/i18n/elfinder.<?php echo $language;?>.js"></script>
        <?php } ?>

        <script type="text/javascript" charset="utf-8">
        function getUrlParam(paramName) {
            var reParam = new RegExp('(?:[\?&]|&amp;)' + paramName + '=([^&]+)', 'i') ;
            var match = window.location.search.match(reParam) ;
            
            return (match && match.length > 1) ? match[1] : '' ;
        }
        
        var customData = {};
        <?php if (Yii::app()->request->enableCsrfValidation) { ?>
        customData['<?php echo Yii::app()->request->csrfTokenName;?>'] = '<?php echo Yii::app()->request->csrfToken;?>';
        <?php } ?>
        
        $().ready(function() {
            var funcNum = getUrlParam('CKEditorFuncNum');
            
            var elf = $('#elfinder').elfinder({
                url : '<?php echo $connectorUrl;?>',
                lang: '<?php echo !empty($language) ? $language : 'en';?>',
                customData: customData,
                getFileCallback : function(file) {
                    window.opener.CKEDITOR.tools.callFunction(funcNum, file);
                    window.close();
                },
                resizable: false
            }).elfinder('instance');
        });
        </script>
	</head>
	<body>
        <div id="elfinder"></div>
	</body>
</html>
