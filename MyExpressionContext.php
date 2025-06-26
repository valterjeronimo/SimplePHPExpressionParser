<?php require_once('ExpressionParser.php'); ?>
<?php

class MyExpressionContext extends ExpressionContext
{	
	public function EvalutateFunction(string $name, array $Params)
	{
		$nm=strtolower(trim($name));
		
		if(sizeof($Params)!=1)
			return $this->NewError("Invalid number of arguments");
		
		if($nm=='sin')
			return sin($Params[0]);
		if($nm=='cos')
			return cos($Params[0]);
		
		return $this->NewError("Unknown function '". $name ."'");
	}
}

?>
</body>
</html>
