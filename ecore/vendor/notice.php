<?php
if(!function_exists('ecore_get_exception_source')){
    function ecore_get_exception_source($file, $line,$step=5){
        if (!(file_exists($file) && is_file($file))) {return '';}
        $data = file($file);$count = count($data) - 1;
        $start = $line - $step;if ($start < 1) {$start = 1;}
        $end = $line + $step;if ($end > $count) {$end = $count + 1;}
        $returns = array();
        for ($i = $start; $i <= $end; $i++) {
            $code = $data[$i - 1];
            if(!$code){
                continue;
            }
            if( $i == $line ){
                $returns[] = "<div class='current'>".$i.".&nbsp;".highlight_string($code, TRUE)."</div>";
            }else{
                $returns[] = $i.".&nbsp;".highlight_string($code, TRUE);
            }
        }
        return $returns;
    }
}
?>
<!DOCTYPE html>
<html lang="zh_CN">
<head>
    <meta name="robots" content="noindex, nofollow, noarchive" />
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title><?php echo $exception->getMessage();?></title>
    <style>
        body{padding:0;margin:0;font-family:Arial, Helvetica, sans-serif;background:#EBF8FF;color:#5E5E5E;}
        div, h1, h2, h3, h4, p, form, label, input, textarea, img, span{margin:0; padding:0;}
        ul{margin:0; padding:0; list-style-type:none;font-size:0;line-height:0;}
        #body{width:100%;margin:0 auto;}
        #main{width:90%;margin:13px auto 0 auto;padding:0 0 35px 0;}
        #contents{width:100%;float:left;margin:13px auto 0 auto;background:#FFF;padding:8px 0 0 9px;}
        #contents h2{display:block;background:#CFF0F3;font-weight:bold;font-size:20px;padding:12px 0 12px 30px;margin:0 10px 22px 1px;}
        #contents ul{padding:0 0 0 18px;font-size:0;line-height:0;}
        #contents ul li{display:block;padding:0;color:#8F8F8F;background-color:inherit;font:normal 14px Arial, Helvetica, sans-serif;margin:0;}
        #contents ul li span{display:inline-block;color:#408BAA;background-color:inherit;font:bold 14px Arial, Helvetica, sans-serif;padding:0 0 10px 0;margin:0;}
        .oneborder{width:90%;font:normal 14px Arial, Helvetica, sans-serif;border:#EBF3F5 solid 4px;margin:0 30px 20px 30px;padding:10px 20px;line-height:23px;}
        .oneborder span{padding:0;margin:0;}
        .oneborder div.current{background:#CFF0F3;}
        #contents ul li span span.err_line{display: inline;color:#f00;font-weight: bold}
        div.trace_details ul li span.pointer{cursor:pointer}
    </style>
</head><body>
<div id="main"><div id="contents">
        <h2><?php echo htmlspecialchars($exception->getMessage());?></h2>
        <ul>
            <li>
                <span><?php echo $exception->getFile();?>【<span class="err_line"><?php echo $exception->getLine();?></span>】</span></li></ul>
        <div class="oneborder">
            <?php
            $souceline = ecore_get_exception_source($exception->getFile(),$exception->getLine(),10);
            foreach( $souceline as $singleline ){
                echo $singleline;
            }
            ?>
        </div>
        <div class="trace_details">
            <?php foreach( $exception->getTrace() as $index=>$trace ){
                if(is_array($trace)&&!empty($trace["file"])){
                    $souceline = ecore_get_exception_source($trace["file"], $trace["line"]);
                    if( $souceline ){
                        ?>
                        <ul><li><span class="pointer"><?php echo $index+1;?>、<?php echo $trace["file"];?> 【<span class="err_line"><?php echo $trace["line"];?></span>】</span></li></ul>
                        <div class="oneborder oneborder_more">
                            <?php
                            foreach( $souceline as $singleline ){
                                echo $singleline;
                            }
                            ?>
                        </div>
                        <?php
                    }
                }
            }
            ?>
        </div>
    </div></div>
<script type="text/javascript">

</script>
</body></html>