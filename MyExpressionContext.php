<?php require_once('ExpressionParser.php'); ?>
<?php

class MyExpressionContext extends BaseExpressionContext
{	
	public function EvalutateFunction(string $name, array $Params)
	{
		$nm=strtolower(trim($name));
		
		if($this->InNameSet($name,['sin','cos','tan','atan']))
		{
			if(sizeof($Params)!=1)
				return $this->NewError("Invalid number of arguments");
			
			if($nm=='sin')
				return sin($Params[0]);
			if($nm=='cos')
				return cos($Params[0]);
			if($nm=='tan')
				return tan($Params[0]);
			if($nm=='atan')
				return atan($Params[0]);
			return $this->NewError("Unknown function '". $name ."'");
		}
		
		return $this->NewError("Unknown function '". $name ."'");
	}
}

?>
</body>
</html>
