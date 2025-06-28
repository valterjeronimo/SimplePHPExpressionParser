<?php require_once('ExpressionParser.php'); ?>
<?php require_once('MyExpressionContext.php'); ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <title>Expression Parser</title>
</head>
<body>
<?php
$Expr=null;
if(isset($_POST['Expr']))
	$Expr=$_POST['Expr'];

?>
<form method="post">
<input name="Expr" type="text" value="<?=$Expr?>">
<input type="submit" value="Parse">
</form>

<?php

if(isset($_POST['Expr']))
{
	$offset=0;
	$NextOffset=0;
	//Parses the expression
	$res= ExpressionParser::Parse($Expr);
	
	//Expression Context handles functions and stores variables
	$Ctx=new MyExpressionContext();
	
	//Expression Context handles functions and stores variables
	$Ctx->SetValue('abc',3);
	$Ctx->SetValue('abcd',4);
	
	if(!is_null($res))
		if($res->IsError())
		{
			echo $res;
			echo '[' . $res->Position . ']';
		}
		else{
			
			echo $res;
			echo '<br>';
			echo '------';
			echo $res->Evaluate($Ctx);
		}
}


?>
</body>
</html>
