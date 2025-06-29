<?php


class ExpressionParser
{
	

	public static function LiteralExpression(string $type, string $value,int $position):ExpressionItem
	{
		$res=new ExpressionItem();
		$res->Type=$type;
		$res->Value=$value;
		$res->Position=$position;
		return $res;
	}

	public static function UnaryOperation(ExpressionToken $opr, $term):ExpressionItem
	{
		if($opr->IsError())
			return $opr;
		if($term->IsError())
			return $term;
		$res=new ExpressionItem();
		$res->Type='unary';
		$res->Value=$opr->Value;
		array_push($res->Children,$term);
		return $res;
	}

	public static function BinaryOperation(ExpressionToken $opr, $left, $right):ExpressionItem
	{
		if($opr->IsError())
			return $opr;
		if($left->IsError())
			return $left;
		if($right->IsError())
			return $right;
		$res=new ExpressionItem();
		$res->Type=$opr->Type;
		$res->Value=$opr->Value;
		array_push($res->Children,$left);
		array_push($res->Children,$right);		
		return $res;
	}

	public static function OperatorPrecedence(ExpressionToken $opr):int
	{
		switch ( strtolower(trim( $opr->Value)))
		{
			case 'or': return 1;
			case 'and': return 2;
			case '!=':
			case '=':
				return 3;
			case '<':
			case '<=':
			case '>':
			case '>=':
				return 4;
			case '+':
			case '-': return 5;
			case '*':
			case '/':
			case '%': return 6;
			case '^': return 7;
			case 'not':
				return 8;
		}
		return -1;
	}
	
	public static function Parse(string $expression):?BaseExpressionItem
	{
		$lexer=ExpressionLexer::ParseTokens($expression);
		if($lexer->EOFPosition==0)
			return null;

		if(!is_null( $lexer->Error))
			return $lexer->Error;
		
		return ExpressionParser::ParseExpression($lexer);
	}
	
	public static function ParseExpression(ExpressionLexer $lexer):?BaseExpressionItem
	{

		$res=array();
		while(true)
		{
			$term=ExpressionParser::ParseTerm($lexer);
			
			if($term->IsError())
				return $term;

			array_push($res,$term);
			$opr=$lexer->PeekToken();
			if(is_null($opr))
				break;

			if($opr->IsType('opr','lopr','copr'))
			{
				array_push($res,$opr);
				$lexer->SkipToken();
			}
			else if($opr->IsValue(',',')'))
				break;
			else
				return BaseExpressionItem::NewError("Wasn't expecting '" . $opr->Value ."' at this time.", $opr->Value, $opr->Position);
 		}
		
		while(sizeof($res)>1)
		{
			$currentOpr = -1;
			$currentPrecedence = -1;

			for ($i = 0; $i < sizeof($res); $i++)
			{
				$itm = $res[$i];
				if ($itm->IsToken())
				{
					$pre = ExpressionParser::OperatorPrecedence($itm);
					if($pre<0)
						return BaseExpressionItem::NewError('Unkown operator precedence', $itm->Value, $itm->Position);
					if ($pre > $currentPrecedence)
					{
						$currentPrecedence = $pre;
						$currentOpr = $i;
					}
				}
			}

			$left = $res[$currentOpr - 1];
			$right = $res[$currentOpr + 1];
			$tkn = $res[$currentOpr];

			$opr =ExpressionParser::BinaryOperation($tkn,$left,$right);

			
			array_splice($res,$currentOpr - 1,3,[$opr]);
		}
		
		return $res[0];		
	}
	
	public static function ParseTerm(ExpressionLexer $lexer):?BaseExpressionItem
	{
		if($lexer->Finished())
			return BaseExpressionItem::NewError('End of expression was not expected at this time', '', $lexer->EOFPosition);

		$T = $lexer->NextToken();
		if ($T->IsValue('null'))
			return ExpressionParser::LiteralExpression('null',  'null', $T->Position);
		
		if ($T->IsValue('true','false'))
			return ExpressionParser::LiteralExpression('bool',  $T->Value, $T->Position);
		
		if ($T->IsType('string','number','integer'))
			return ExpressionParser::LiteralExpression($T->Type, $T->Value, $T->Position);

		if ($T->IsValue(')'))
			return BaseExpressionItem::NewError("Wan't expecting ')' at this time" , ')' , $T->Position);

		if ($T->IsValue(','))
			return BaseExpressionItem::NewError("Wan't expecting ',' at this time" , ',' , $T->Position);

		if ($T->IsValue('('))
		{
			$sub=ExpressionParser::ParseExpression($lexer);
			
			if($sub->IsError())
				return $sub;

			$pt=$lexer->NextToken();
			
			if (!$pt->IsValue(')'))
				return BaseExpressionItem::NewError("Expected ')' but found '" . $pt->Value  ."'", $pt->Value , $pt->Position);
			return $sub;
		}

		
		if ($T->IsValue('not','-'))
		{
			$pt=$lexer->PeekToken();
			if ($pt->IsType('opr','copr','lopr','null'))
				return ExpressionItem::NewError("Wasn't expecting '". $pt->Value ."' at this time",$pt->Value,$pt->Position);
			if ($pt->IsType('string'))
				return ExpressionItem::NewError("Wasn't expecting a string at this time",$pt->Value,$pt->Position);
			$subTerm=ParseTerm($lexer);
			return ExpressionParser::UnaryOperation($T,$subTerm);
		}
		
		if ($T->IsType('ident'))
		{
			$pt=$lexer->PeekToken();
			if(is_null($pt))
				return ExpressionParser::LiteralExpression('variable', $T->Value, $T->Position);

			if ($pt->IsValue('('))
			{
				$lexer->SkipToken();
				$pt=$lexer->PeekToken();
				if ($pt->IsValue(')'))
					return ExpressionParser::LiteralExpression('function', $T->Value, $T->Position);

				$res= ExpressionParser::LiteralExpression('function', $T->Value, $T->Position);
				while(true)
				{
					$sub=ExpressionParser::ParseExpression($lexer);

					if($sub->IsError())
						return $sub;
					
					array_push($res->Children,$sub);
					
					$pt=$lexer->NextToken();

					if ($pt->IsValue(')'))
						break;

					if (!$pt->IsValue(','))
						return BaseExpressionItem::NewError("Expected ')' or ',' but found '" . $pt->Value  ."'", $pt->Value , $pt->Position);
				}				
				
				return $res;
			}
			else
				return ExpressionParser::LiteralExpression('variable', $T->Value, $T->Position);
		}
				
		return BaseExpressionItem::NewError("Unhandled token exception",$T->Value,$T->Position);
	}
	
}

interface IExpressionContext
{
	public function SetValue(string $name, $value);

	public function HasValue(string $name):bool;
	
	public function GetValue(string $name);
	
	public function EvalutateFunction(string $name, array $Params);

}

class BaseExpressionContext implements IExpressionContext
{
	private $Values=array();
	
	public function SetValue(string $name, $value)
	{
		$this->Values[strtolower(trim($name))]=$value;
	}

	public function HasValue(string $name):bool
	{
		return isset( $this->Values[strtolower(trim($name))]);
	}
	
	public function GetValue(string $name)
	{		
		return $this->Values[strtolower(trim($name))];
	}
	
	public function EvalutateFunction(string $name, array $Params)
	{
		return null;
	}
	
	public function InNameSet(string $name, array $set)
	{
		$myName=strtolower( trim($name));
		foreach ($set as $tp)
		{
			if(!is_null($tp))
			{
				if( strtolower( trim($tp))==$myName)
					return true;
			}
		}
		return false;
	}
	
	public function NewError(string $message)
	{
		return BaseExpressionItem::NewError($message,-1);
	}
}

class BaseExpressionItem
{
	public string $Type;
	public int $Position=0;		
	public string $Value;

	public function IsToken():bool{
		return false;
	}

	public function IsExpression():bool{
		return false;
	}

	public function IsError():bool
	{
		return false;
	}
	
	public function IsType(...$type):bool
	{
		$myType=strtolower( trim($this->Type));
		foreach ($type as $tp)
		{
			if(!is_null($tp))
			{
				if( strtolower( trim($tp))==$myType)
					return true;
			}
		}
		return false;
	}


	public function IsValue(...$value):bool
	{
		$myValue=strtolower( trim($this->Value));

		foreach ($value as $tp)
		{
			if(!is_null($tp))
			{
				if( strtolower( trim($tp))==$myValue)
					return true;
			}
		}
		
		return false;
	}
	
	public static function NewError(string $message, int $offset)
	{
		$res=new ExpressionError();
		$res->Type='Error';
		$res->Value=$message;
		$res->Position=$offset;		
		return $res;
	}

}


class ExpressionToken extends BaseExpressionItem
{
	public function IsToken():bool
	{
		return true;
	}
	

	public function __toString():string
	{
		return '[' . $this->Type . ']' . $this->Value;
	}
	
	public static function NewToken(string $type, string $value, int $offset)
	{
		$res=new ExpressionToken();
		$res->Value=$value;
		$res->Type=$type;
		$res->Position=$offset;		
		return $res;
	}	
}



class ExpressionError extends BaseExpressionItem
{
	public function IsError():bool
	{
		return true;
	}
	
	public function __toString():string
	{
		return '(Error[' . $this->Position . ']: ' . $this->Value . ')';
	}	
}




class ExpressionItem extends BaseExpressionItem
{
	public array $Children=array();
	
	public function IsExpression():bool{
		return true;
	}

	public function __toString():string
	{
		$res='[' . $this->Type . ']' . $this->Value;
		
		if(sizeof($this->Children)>0)
		{
			if($this->IsType('function'))
				$res =$res . '(';
			else
				$res =$res . '{ ';
						
			$res =$res . implode(', ', $this->Children);
						
			if($this->IsType('function'))
				$res =$res . ')';
			else
				$res =$res . ' }';
		}
		
		return $res;
	}

	public function Evaluate(IExpressionContext $Context)
	{
		if($this->IsType('null'))
			return null;
		if($this->IsValue('true'))
			return true;
		if($this->IsValue('false'))
			return false;
		
		if($this->IsType('integer'))
			return intval($this->Value);

		if($this->IsType('number'))
			return filter_var($this->Value, FILTER_VALIDATE_FLOAT);

		if($this->IsType('string'))
			return substr($this->Value,1,strlen($this->Value)-2);

		if($this->IsType('variable'))
		{
			if(!$Context->HasValue($this->Value))
				return BaseExpressionItem::NewError("Unknown variable '" . $this->Value . "'",  $this->Position);
			
			$v=$Context->GetValue($this->Value);
			if(is_string($v))
				return $v;
			if(is_integer($v))
				return $v;
			if(is_float($v))
				return $v;
			if(is_bool($v))
				return $v;
			if(is_null($v))
				return $v;
			return BaseExpressionItem::NewError("Invalid value type for argument '" . $this->Value . "'",  $this->Position);
		}
		
		$Params=array();
		foreach($this->Children as $ch)
		{
			$P=$ch->Evaluate($Context);
			if($P instanceOf ExpressionError)
				return $P;
			array_push($Params,$P);
		}
		
		if($this->IsType('function'))
		{
			$v= $Context->EvalutateFunction($this->Value,$Params);
			
			if($v instanceOf ExpressionError)
			{
				$v->Position=$this->Position;
				return $v;
			}

			if(is_string($v))
				return $v;
			if(is_integer($v))
				return $v;
			if(is_float($v))
				return $v;
			if(is_bool($v))
				return $v;
			if(is_null($v))
				return $v;
			return BaseExpressionItem::NewError("Invalid result for function '" . $this->Value . "'", $this->Position);
		}

		if($this->IsValue('+'))
		{
			if(is_string($Params[0]))
				return $Params[0] . $Params[1];

			if(!is_numeric($Params[0]))
				return BaseExpressionItem::NewError(" operation '" .  $this->Value . "' should only be made between two numbers", $this->Position);

			if(!is_numeric($Params[1]))
				return BaseExpressionItem::NewError(" operation '" .  $this->Value . "' should only be made between two numbers", $this->Position);

			return $Params[0]+$Params[1];
		}

		if($this->IsValue('and','or'))
		{
			if(!is_int($Params[0]) && !is_bool($Params[0]))
				return BaseExpressionItem::NewError(" operation '" .  $this->Value . "' should only be made between two booleans", $this->Position);

			if(!is_int($Params[1]) && !is_bool($Params[1]))
				return BaseExpressionItem::NewError(" operation '" .  $this->Value . "' should only be made between two booleans", $this->Position);
			
			$L=$Params[0];
			$R=$Params[1];
			
			if(is_int($L))
				$L=$L!=0;

			if(is_int($R))
				$R=$R!=0;
			
			if($this->IsValue('and'))
				return $L && $R;
			return $L || $R;
		}
		
		if($this->IsValue('-','*','/','^'))
		{
			if(!is_numeric($Params[0]))
				return BaseExpressionItem::NewError(" operation '" .  $this->Value . "' should only be made between two numbers",  $this->Position);

			if(!is_numeric($Params[1]))
				return BaseExpressionItem::NewError(" operation '" .  $this->Value . "' should only be made between two numbers", $this->Position);

			if($this->IsValue('-'))
				return $Params[0]-$Params[1];

			if($this->IsValue('*'))
				return $Params[0]*$Params[1];

			if($this->IsValue('^'))
			{
				if(floatval($Params[1])==((float)0))
					return BaseExpressionItem::NewError("Exponent's base can't be zero", $this->Position);
				return pow($Params[0],$Params[1]);
			}
			
			if($this->IsValue('/'))
			{
				if(floatval($Params[1])==((float)0))
					return BaseExpressionItem::NewError("Can't divide by zero",  $this->Position);
					
				return $Params[0]/$Params[1];
			}
		}

		if($this->IsValue('%'))
		{			
			if(!is_integer($Params[0]))
				return BaseExpressionItem::NewError(" operation '" .  $this->Value . "' should only be made between two ingegers", $this->Position);

			if(!is_integer($Params[1]))
				return BaseExpressionItem::NewError(" operation '" .  $this->Value . "' should only be made between two ingegers",  $this->Position);

			return intval($Params[0]) % intval($Params[1]);
		}
		
		if($this->IsValue('<'))
			return $Params[0]<$Params[1];

		if($this->IsValue('>'))
			return $Params[0]>$Params[1];
		
		if($this->IsValue('<='))
			return $Params[0]<=$Params[1];

		if($this->IsValue('>='))
			return $Params[0]>=$Params[1];

		if($this->IsValue('='))
			return $Params[0]===$Params[1];
		
		if($this->IsValue('!='))
			return $Params[0]!=$Params[1];
		
		return BaseExpressionItem::NewError("Unknown operation for evaluateion  '" . $this->Value . "' ",  $this->Position);
	}
}


class ExpressionLexer
{
	public array $Tokens=array();
	public int $Position=0;
	public int $EOFPosition=0;
	public $Error=null;
	
	public function Finished():bool
	{
		return $this->Position>=sizeof($this->Tokens);
	}
	
	public function SkipToken()
	{
		$this->Position=$this->Position+1;
	}
	
	public function NextToken():?BaseExpressionItem
	{
		if($this->Position>=sizeof($this->Tokens))
			return null;
		$res=$this->Tokens[$this->Position];
		$this->Position=$this->Position+1;
		return $res;
	}

	public function PeekToken():?BaseExpressionItem
	{
		if($this->Position>=sizeof($this->Tokens))
			return null;
		$res=$this->Tokens[$this->Position];
		return $res;
	}
	
	
		static function IsAnyToken(?string $haystack,string $type,int $offset, array $options):?ExpressionToken
		{
			for($i=0;$i<sizeof($options);$i++)
			{
				if(substr($haystack,$offset, strlen($options[$i]))==$options[$i])
					return ExpressionToken::NewToken($type,$options[$i],$offset);
			}
			return null;
		}

		static function IsTokenPattern(?string $code,string $pattern,int $offset):?string
		{
			$matches=array();
			if(preg_match($pattern,$code,$matches,PREG_OFFSET_CAPTURE,$offset))
				if($matches[0][1]==$offset)
					return $matches[0][0];
			return null;
		}
		
		public static function ParseNextToken(?string $code,int $offset):?ExpressionToken
		{
			if(is_null($code))
				return null;
			if($offset>=strlen($code))
				return null;

			$res=ExpressionLexer::IsTokenPattern($code,'/[ ]+/i',$offset);
			if(!is_null($res))
				return ExpressionToken::NewToken('space', $res, $offset);
	
			$copr=['<=','>=','!=','<','>','='];
			
			$res=ExpressionLexer::IsAnyToken($code,'copr',$offset,['<=','>=','!=','<','>','=']);
			if(!is_null($res))
				return $res;

			$res=ExpressionLexer::IsAnyToken($code,'opr',$offset,['+','-','*','/','%','^']);
			if(!is_null($res))
				return $res;

			if(substr($code,$offset,1)==',')
				return ExpressionToken::NewToken('comma',',',$offset);
			if(substr($code,$offset,1)=='(')
				return ExpressionToken::NewToken('lparam','(',$offset);
			if(substr($code,$offset,1)==')')
				return ExpressionToken::NewToken('rparam',')',$offset);

			if(substr($code,$offset,1)=="'")
			{
				$nextPos=strpos($code,"'",$offset+1);
				if($nextPos>0)
					return ExpressionToken::NewToken('string', substr($code,$offset,$nextPos+1-$offset), $offset);
				return BaseExpressionItem::NewError('Unterminated string', $offset);
			}

			$res=ExpressionLexer::IsTokenPattern($code,'/[a-zA-Z][a-zA-Z0-9_]*/i',$offset);
			if(!is_null($res))
			{
				if(strtolower($res)=='and' || strtolower($res)=='or' || strtolower($res)=='not')
					return ExpressionToken::NewToken('lopr', strtolower($res), $offset);
				return ExpressionToken::NewToken('ident', $res, $offset);
			}

			$res=ExpressionLexer::IsTokenPattern($code,'/[0-9]+[\.][0-9]+[a-zA-Z_]/i',$offset);			
			if(!is_null($res))
				return BaseExpressionItem::NewError("Invalid number '$res', need separation between number and letters", $offset);

			$res=ExpressionLexer::IsTokenPattern($code,'/[0-9]+[a-zA-Z_]/i',$offset);			
			if(!is_null($res))
				return BaseExpressionItem::NewError("Invalid number '$res' need separation between number and letters",  $offset);

			$res=ExpressionLexer::IsTokenPattern($code,'/[0-9]+[\.][0-9]+/i',$offset);			
			if(!is_null($res))
				return ExpressionToken::NewToken('number', $res, $offset);

			$res=ExpressionLexer::IsTokenPattern($code,'/[0-9]+[\.]/i',$offset);			
			if(!is_null($res))
				return BaseExpressionItem::NewError("Invalid number '$res', Missing digits after decimal point", $offset);

			$res=ExpressionLexer::IsTokenPattern($code,'/[0-9]+/i',$offset);			
			if(!is_null($res))
				return ExpressionToken::NewToken('integer', $res, $offset);
			
			return BaseExpressionItem::NewError("Invalid character '" .  substr($code,$offset,1) . "' at this position",  $offset);
		}
	
	
		public static function ParseTokens(?string $code):ExpressionLexer
		{
			$res=new ExpressionLexer();
			$offset=0;
			$NextOffset=0;
			while(true)
			{
				$offset=$NextOffset;
				$tkn=ExpressionLexer::ParseNextToken($code,$offset);
				if(!is_null($tkn))
				{
					$NextOffset=$offset+strlen($tkn->Value);
					if(strtolower($tkn->Type)!='space')
						array_push($res->Tokens,$tkn);
					if($tkn->IsError())
					{
						$res->Error=$tkn;
						break;
					}
				}
				else 
					break;		
			}
			
			if(sizeof($res->Tokens)>0)
			{
				$lastToken=$res->Tokens[sizeof($res->Tokens)-1];
				$res->EOFPosition=$lastToken->Position+strlen($lastToken->Value);
			}

			return $res;
		}

}



?>