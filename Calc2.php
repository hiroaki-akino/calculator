<?php

/*
=============================================
作成者：秋野浩朗
作成日：2020/7/22
修正日：2020/8/4
概要　：自由入力された式の判定と計算
=============================================

■ 参考URL
PHP		全角半角変換											https://techacademy.jp/magazine/21718
PHP		MBコンバート関数について					 https://blog.codecamp.jp/programming-php-mb_convert_kana
PHP		日本語（マルチバイト）の文字列分割	https://thr3a.hatenablog.com/entry/20131027/1382849078
PHP		厳密な型変換											https://php.plus-server.net/types.comparisons.html
PHP		String型の文字列の整数（int）判定		https://www.php.net/manual/ja/function.ctype-digit.php
js		テキストコピー操作								 https://qiita.com/kwst/items/8d9cd40e181761085325
js		select()												 http://alphasis.info/2014/02/javascript-dom-textarea-select/
js		小数→分数の変換アルゴリズム				 https://www.vcssl.org/ja-jp/code/archive/0001/2900-float-to-fraction/FloatToFraction.html
js		XML形式の値挿入方法								https://www.w3schools.com/xml/prop_element_textcontent.asp
HTML	分数の表示方法										https://it-engineer-lab.com/archives/825
HTML	分数の表示方法										https://reference.wolfram.com/language/XML/tutorial/MathML.html.ja?source=footer
その他	文字コード表 JISコード					 http://charset.7jp.net/jis.html
その他	文字コード16進ダンプ変換				 http://charset.7jp.net/dump.html
その他	分数の計算方法									http://www.bestware.jp/2017/09/23/post-438/

*/ 


// ＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝　変数（ローカルファイル内の共通変数）＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝

$source_name			= basename(__FILE__);
$delimiter				= "<br><hr><br>";
$err_msg					= null;
$err_flug					= false;	// 計算ができなかった場合のフラグ
$redisplay_value	= "";			// 再表示用の入力された値（クオーテーション等をHTMLで表示できるようにエスケープ処理）
$result						= "";			// 実際の計算結果
$display_result		= "";			// 表示する用の計算結果（小数点は第５位で四捨五入）
$float_flug				= false;	// 結果がFloat型かどうか（Float型なら小数点第５位で四捨五入した値と分数を表示する）
$display_calc_process_flug = false; // (おまけ)計算過程を表示するかどうかのフラグ

// 入力例の一覧
$ex = array(
	array( "ex" => "1+1=",														"answer" =>1),
	array( "ex" => "＝  0.25 + 2.25 - 1",							"answer" =>"1.5 (3/2)"),
	array( "ex" => "（1+３）÷  5",					 					"answer" =>"0.8 (4/5)"),
	array( "ex" => "（（1+３）- 5 × 2) ÷ 2", 					"answer" =>-3),
	array( "ex" => "（１０％３ - ( -5 + 2^2) ) ＊ 5",	 "answer" =>10),
	array( "ex" => "3! - １",													"answer" =>5),
	array( "ex" => "2^2 + -2^2 + (-2)^2",							"answer" =>4),
	array( "ex" => "3^0 × 3! % 7 ",										"answer" =>6),
	array( "ex" => "2^-8 × 2^6 ",											"answer" =>"0.25（1/4）"),
	array( "ex" => "-2^2 + (-2)^3 ",									"answer" =>"-12"),
	array( "ex" => "-2３ + -5 ",											"answer" =>-28),
	array( "ex" => "（-9 + ＋３）×　- 5 ",						 "answer" =>30),
	array( "ex" => "2 + 3 > 6 ",											"answer" =>"偽 (false)"),
	array( "ex" => "3!  ==  5 + 1 ",									"answer" =>"真 (true)"),
	array( "ex" => "\"あ\" > \"い\" ",								"answer" =>"偽 (false)"),
	array( "ex" => "\"A\" > \"a\" ",									"answer" =>"偽 (false)"),
	array( "ex" => "\"あ\" == \"あ\"  || \"あ\" != \"あ\"",												"answer" =>"真 (true)"),
	array( "ex" => "(\"あ\" == \"あ\" || \"あ\" != \"あ\" ) && \"あ\" == \"q\"",	"answer" =>"偽 (false)"),
	array( "ex" => "(\"あ\" == \"あ\" ) × 2",																			"answer" =>2),
	array( "ex" => "(\"あ\" != \"あ\" ) + 5",																			"answer" =>5),
	array( "ex" => "( (\"あ\" == \"あ\") × 2 ) ÷ ( (\"あ\" != \"あ\") + 5 ) ",		"answer" =>"0.4 ( 2/5 )")
);

// テストケースの一覧（結果は全部「計算不能」になる）
$err_case = array(
	array( "err" => "	1 +							"),
	array( "err" => "	1 + あ					"),
	array( "err" => "	1 + \"あ\"			"),
	array( "err" => "	1 + +						"),
	array( "err" => "	\"あ\"\"				"),
	array( "err" => "	'あ' '					"),
	array( "err" => "	+\"あ\"					"),
	array( "err" => "	/1							"),
	array( "err" => "	------1					"),
	array( "err" => "	1 ~ 1						"),
	array( "err" => "	( 1 + 1 ) + (		"),
	array( "err" => "	( 1 + 1 ) + ( )	"),
	array( "err" => "	1^a							"),
	array( "err" => "	-2!							"),
	array( "err" => "	\"あ\"!					")
);


// ＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝　関数（ローカルファイル内の共通関数）＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝


/*【関数】サニタイズ（XSS対策） */
function h($val){
	return htmlspecialchars($val);
}

/*【関数】検証用（ preタグ + var_dump() ）*/
function pre_dump($arg){
	echo "<pre>";
	var_dump($arg);
	echo "</pre>";
}

//【関数】配列の添え字を0から振りなおす
// 引数：配列
// 返値：添字を0から振り直した同じ配列
function alignment_array($array){
	$i = 0;
	$result_array = array();
	foreach($array as $val){
		$result_array[$i] = $val;
		$i++;
	}
	return $result_array;
}

//【関数】入力値を最表示する時にクオーテーションをHTML上で表示できるようにエスケープ記号に置き換える。
// 引数：文字列
// 返値：クオーテーションをHTMLでも表示できるエスケープ記号に置き換えた文字列
function redisplay_escape($array){
	$string_array = str_split($array);
	$result = "";
	for($i = 0 ; $i < count($string_array) ; $i++){
		switch($string_array[$i]){
			case "'" :
				$result .= "&#39;";
				break;
			case '"':
				$result .= "&quot;";
				break;
			// case '’':
			// 	$result .= "\\".$string_array[$i];
			// 	break;
			// case '”':
			// 	$result .= "\\".$string_array[$i];
			// 	break;
			default:
				$result .= $string_array[$i];
				break;
		}
	}
	return $result;
}

//【関数】mb_conbert_kana()で全角 → 半角変換できない文字を半角に変換（全角クオーテーション等）。
// 引数：文字列
// 返値：文字列
// 備考：preg_split()で全角文字（SJIS）も1つの要素に「UTF-8」で分割、16進数に変換して比較する。
// ちなみに全角文字（SJIS）はマルチバイト（3バイト扱い）なので、str_split()で分割すると3つの配列になってしまう。
// 1文字ずつ1つの要素に分割した文字を16進ダンプと比較して必要に応じて変換する。
function calc_escape($array){
	$string_array = preg_split("//u", $array, -1, PREG_SPLIT_NO_EMPTY);
	// $string_array =  str_split($array);
	$result = "";
	for($i = 0 ; $i < count($string_array) ; $i++){
		// echo $string_array[$i], ",encode:", mb_detect_encoding($string_array[$i]),
		// 			",ord:", ord($string_array[$i]), "hex:",bin2hex($string_array[$i]),"<br>";
		switch(true){
			//「 ’（全角シングルクォーテーション）」の場合（16進で「UTF-8」で「e2,80,99」の合算値）
			case bin2hex($string_array[$i]) == "e28099" :
				$result .= "\'";
				break;
			//「 ”（全角ダブルクォーテーション）」の場合（16進で「UTF-8」で「e2,80,9d」の合算値）
			case bin2hex($string_array[$i]) == "e2809d" :
				$result .= "\"";
				break;
			//「÷」の場合（16進で「UTF-8」で「c3,b7」の合算値）
			case bin2hex($string_array[$i]) == "c3b7" :
				$result .= "/";
				break;
			//「×」の場合（16進で「UTF-8」で「c3,97」の合算値）
			case bin2hex($string_array[$i]) == "c397" :
				$result .= "*";
				break;
			default:
				$result .= $string_array[$i];
				break;
		}
	}
	return $result;
}

//【関数】引数をUTF-8にエンコード＆16進（ダンプ）に変換。
// 引数：データ
// 返値：16進数
// 備考：元々UTF-8で入ってるはずだが一応変換。
function convert_utf_8_hex($data){
	global $display_calc_process_flug;
	// if(!is_int($data) && !is_float($data) && !is_bool($data) ){
		$data = mb_convert_encoding($data, "UTF-8");
		if($display_calc_process_flug){
			echo "データ：", $data , " / 16進数：" , bin2hex($data) , "<br>";
		}
		return bin2hex($data);
	// }else{
	// 	return $data;
	// }
}

//【関数】入力値の式内容に誤りがないか最低限のチェックをする
// 引数：文字列
// 返値：True/False
// 備考：必要に応じてエラーメッセージを格納。
function valid($calc_string_array){
	global $err_msg, $err_flug;
	$kakko_count				= 0;
	$double_quote_count	= 0;
	$single_quote_count	= 0;

	for($i = 0 ; $i < count($calc_string_array) ; $i++){
		switch($calc_string_array[$i]){
			case "+" :
			case "-" :
			case "*" :
			case "/" :
			case "÷" :
			case "^" :
				// 四則演算演算子の次には何かしらの文字がないとおかしい（＝四則演算の演算子が最後の文字になる式はありえない）
				if( !isset($calc_string_array[$i + 1]) ){
					$err_msg .= "四則演算の記入方法に誤りがあります。";
					$err_flug	= true;
					return false;
					break;
				}
				break;
			case "(" :
				$kakko_count++;
				break;
			case ")" :
				$kakko_count--;
				break;
			case "\"" :
				$double_quote_count++;
				break;
			case "'" :
				$single_quote_count++;
				break;
			// case "=" :
			// 	//「=」の前には「=」もしくは「!」、または後に「=」がないとおかしい
			// 	if( $calc_string_array[$i-1] != "=" && $calc_string_array[$i-1] != "!" && $calc_string_array[$i+1] != "="){
			// 		$err_msg .= "等号の式に誤りがあります（「==」もしくは「!=」で記載して下さい）";
			// 		$err_flug	= true;
			// 		return false;
			// 	}
			// 	break;
		}
	}
	//（）の数が一致しないとおかしい
	if($kakko_count != 0){
		$err_msg .= "（）の数が一致しません。";
		$err_flug	= true;
		return false;
	}
	// ダブルクォーテーションの数が一致しないとおかしい
	if($double_quote_count % 2 != 0){
		$err_msg .= "ダブルクオーテーションの数が一致しません。";
		$err_flug	= true;
		return false;
	}
	// シングルクォーテーションの数が一致しないとおかしい
	if($single_quote_count % 2  != 0){
		$err_msg .= "シングルクオーテーションの数が一致しません。";
		$err_flug	= true;
		return false;
	}
	return true;
}

//【関数】入力された文字列を優先順位毎にグループ分けして、計算結果をcalc()から取得する
// 引数：文字列配列
// 返値：calc_divid()の計算結果
// 備考：計算の優先順位を決める為に再帰で（）をグループ分け ＆ グループ毎にcalc_divid()で計算結果を取得
function calc_priority($arg_array, $i){
	global $err_flug, $err_msg;
	while(true){
		// 最後の文字列が削除されていれば（処理されていれば）ブレイク
		if(empty($arg_array)){
			break;
		}
		//「（ 」の場合は優先順位が上がるので、再帰で別処理を行う。
		if($arg_array[$i] == "("){
			// 再帰から帰ってくれば（）内の計算結果が返ってくる。
			$calc_array[] = calc_priority($arg_array, $i+1);
			// 計算途中でエラーがあれば強制終了
			if($err_flug){ return ; }
			// 再帰から帰ってくれば（）内を計算した分の文字列配列を削除して$iを進める。
			$kakko_count = 0;
			for($j = $i ; ; $j++){
				// 再帰から帰ってきた時に（）が二重三重になっている可能性があるので、必要量に応じて削除する。
				if($arg_array[$j] == "("){
					$kakko_count++;
				}
				if($arg_array[$j] == ")"){
					$kakko_count--;
				}
				unset($arg_array[$j]);
				if($kakko_count == 0){
					$i = $j + 1 ;
					break;
				}
			}
			// 最後の文字が「)」の場合（= 文字列配列の次の要素がない時）はブレイク
			if(!isset($arg_array[$i])){
				break;
			}
		}
		//「 ）」の場合は優先順位処理が終了するので再帰を終了させる
		if($arg_array[$i] == ")"){
			if(empty($calc_array)){
				$err_msg	= "（）内に式が入力されていません。";
				$err_flug	= true;
				return ;
			}
			$calc_array = alignment_array($calc_array);
			return calc_divid($calc_array);
		}
		// 計算用配列に文字列配列の次の要素を格納する。
		$calc_array[] = $arg_array[$i];
		unset($arg_array[$i]);
		$i++;
	}
	// echo "result";
	// pre_dump($calc_array);
	return calc_divid($calc_array);
}

//【関数】引数の文字列をデータと演算子に分割して、calc()に引き渡す。
// 引数：文字列配列（（）がない1連続する式）
// 返値：calc()の計算結果/エラー発生時は空値（計算結果がBoolean型の場合もある為、エラー発生時は空を返してエラーフラグをtrueにする）
// 備考：引数[1][0][+][1][*][4][5] → [10][+][1][*][45] → データ配列[10][1][45]、演算子配列[+][*]に格納
function calc_divid($array){
	global $err_flug, $err_msg, $display_calc_process_flug;
	$string_flug			= false;
	$decimal_flug			= false;
	$data_array				= array();
	$operator_array		= array();
	$next_data_no			= 0;
	$data_count				= 0;
	$next_operator_no	= 0;
	$operator_count		= 0;
	$sign							= "";
	$sign_data_no			= array();

	if(count($array) == 1 ){
		return $array[0];
	}

	// 元々の配列（文字列）をデータ配列と演算子配列にグループ分けして分解する。
	// 例：[1][0][+][1][-][4][5] → [10][+][1][-][45]
	for($i = 0 ; $i < count($array) ; $i++){

		// 文字列認識開始
		// 「"」もしくは「'」の場合は次の文字以降は文字列なので文字列フラグをTrueにする
		// 備考：Boolean型の場合は何故かTrueになる？ので厳密な型変換が必要
		if(!$string_flug && ($array[$i] === "\"" || $array[$i] === "\'" ) ){
			$string_flug = true;
			// 「"」もしくは「'」の文字は処理不要なのでContinueさせる
			continue;
		}

		// 文字列認識終了
		// 備考：Boolean型の場合は何故かTrueになる？ので厳密な型変換が必要
		if($string_flug && ($array[$i] === "\"" || $array[$i] === "\'" ) ){
			$string_flug = false;
			//「"」もしくは「'」の文字は処理不要なのでContinueさせる
			continue;
		}

		// 小数点処理開始（文字が演算子に移った時点で処理終了）
		if(!$decimal_flug && $array[$i] === "."){
			$decimal_flug = true;
			// データが続いてる事になるのでデータカウンターを+1させる
			$next_data_no	= $i + 1 ;
			//「.」の文字は処理不要なのでContinueさせる
			continue;
		}

		// 元々の配列（文字列）の文字が、データ文字（数値、True/False、文字列）かどうかを判定
		if( is_numeric($array[$i]) || is_bool($array[$i]) || $string_flug ){

			// データ文字が連続していない場合（1文字目の場合）
			if( $next_data_no == 0 || $i != $next_data_no ){
				// 符号が代入されているかどうかを判定
				if( !empty($sign) ){
					// 符号が入力されている場合は、データ配列のデータを符号に合わせて変更
					// 文字が数値かどうかを判定（符号は数値につくもの）
					if( is_numeric($array[$i]) && !$string_flug){
						if($sign == "-"){
							// 符号が－の時はデータを－に変更して代入（型は自動キャストされるので指定しない）
							$data_array[$data_count]	= $array[$i] * -1;
						}else{
							// 符号が＋の時はそのまま代入（型を指定して代入）
							switch (true){
								case is_int($array[$i]) :
									$data_array[$data_count]	= (int)$array[$i]	;
									break;
								case is_float($array[$i]) :
									$data_array[$data_count]	= (float)$array[$i]	;
									break;
								case is_bool($array[$i]) :
									$data_array[$data_count]	= (bool)$array[$i]	;
									break;
								case is_string($array[$i]) :
									if( ctype_digit($array[$i]) ){
										$data_array[$data_count]	= (int)$array[$i]	;
									}else{
										$data_array[$data_count]	= (float)$array[$i]	;
									}
								break;
							}
						}
					}else{
						// 符号がついているのにデータが数値ではない場合はエラー
						$err_msg	= "符号（+/-）の次の文字は数値で入力して下さい";
						$err_flug	= true;
						return ;
					}
				}else{
					// 符号が指定されていない時はそのまま代入（型を指定して代入）
					if( is_numeric($array[$i]) && !$string_flug){
						switch (true){
							case is_int($array[$i]) :
								$data_array[$data_count]	= (int)$array[$i]	;
								break;
							case is_float($array[$i]) :
								$data_array[$data_count]	= (float)$array[$i]	;
								break;
							case is_bool($array[$i]) :
								$data_array[$data_count]	= (bool)$array[$i]	;
								break;
							case is_string($array[$i]) :
								if( ctype_digit($array[$i]) ){
									$data_array[$data_count]	= (int)$array[$i]	;
								}else{
									$data_array[$data_count]	= (float)$array[$i]	;
								}
							break;
						}
					}else{
						if( is_bool($array[$i]) ){
							$data_array[$data_count] = (bool)$array[$i] ;
						}else{
							$data_array[$data_count] = (string)$array[$i] ;
						}
					}
				}
				// データ文字列カウンターを進める（次の文字がデータ文字かどうかを判定する）
				$next_data_no	= $i + 1 ;

				// 元々の文字列が演算子文字 → データ文字に変わった為、演算子配列の添え字を加算
				if( !empty($operator_array) ){
					$operator_count++;
				}
				// この回（ループ内）で必要な処理は終了したのでのでContinue
				continue;
			}

			// データ文字が連続している場合（2文字目以降の場合）
			if($i == $next_data_no ){
				if($string_flug){
					// 文字列の場合の処理：既に代入されている1文字目に文字列連結して2文字目以降を代入
					$data_array[$data_count] = $data_array[$data_count] . $array[$i];
				}else{
					// 数値の場合の処理：既に代入されている数値の桁を合わせて1要素に代入
					if($sign == "-"){
						// 数値が負の数の場合
						if(!$decimal_flug){
							// 負の「整数」の場合：既に代入されている数値を10倍後、新しい数値を「減算」して、桁を合わせてから1つの要素に代入
							$data_array[$data_count] = (int)$data_array[$data_count] * 10 - (int)$array[$i];
						}else{
							// 負の「小数」の場合：新しい数値を現在の小数点以下の数値の桁数に合わせてから「減算」して、1つの要素に代入
							// 現在の整数値を取得
							$integer	= floor($data_array[$data_count]);
							// 現在の小数点以下の数値を取得
							$fraction	= $data_array[$data_count] - $integer;
							// 小数点以下の数値（例：0.1234）の文字数（桁数）を取得
							$fraction == 0 ? $digit = 0 : $digit = strlen($fraction) - 2 ;
							$mag			= 1;
							// 桁数に合わせて倍率を変更（1：10倍、2：100倍、3：1000倍・・・・）
							for ($j = 0 ; $j <= $digit ; $j++){
								$mag *= 10;
							}
							// 現在の数値から、新しい数値を倍率分割った数を減算する。
							$data_array[$data_count] = (float)$data_array[$data_count] - (float)$array[$i] / $mag;
						}
					}else{
						// 数値が正の数の場合
						if(!$decimal_flug){
							// 正の「整数」の場合：既に代入されている数値を10倍後、新しい数値を「加算」して、桁を合わせてから1つの要素にい代入
							$data_array[$data_count] = (int)$data_array[$data_count] * 10 + (int)$array[$i];
						}else{
							// 正の「小数」の場合：新しい数値を現在の小数点以下の数値の桁数に合わせてから「加算」して、1つの要素に代入
							// 現在の整数値を取得
							$integer	= floor($data_array[$data_count]);
							// 現在の小数点以下の数値を取得
							$fraction	= $data_array[$data_count] - $integer;
							// 小数点以下の数値（例：0.1234）の文字数（桁数）を取得
							$fraction == 0 ? $digit = 0 : $digit = strlen($fraction) - 2 ;
							$mag			= 1;
							// 桁数に合わせて倍率を変更（1：10倍、2：100倍、3：1000倍・・・・）
							for ($j = 0 ; $j <= $digit ; $j++){
								$mag *= 10;
							}
							// 現在の数値から、新しい数値を倍率分割った数を減算する。
							// echo $i, $data_array[$data_count];
							$data_array[$data_count] = (float)$data_array[$data_count] + (float)$array[$i] / $mag;
						}
					}
				}
				$next_data_no	= $i + 1 ;
				// 2文字以上続く場合は演算子配列の添え字数を重複して加算してしまうのでContinue
				continue;
			}

		}else{
		// 演算子文字の場合

			// エラーチェック
			if($array[$i] !== "+" && $array[$i] !== "-" && $array[$i] !== "*" && $array[$i] !== "/" && 
				 $array[$i] !== "%" && $array[$i] !== "^" && 
				 $array[$i] !== ">" && $array[$i] !== "<" && $array[$i] !== "=" && $array[$i] !== "!" && 
				 $array[$i] !== "|" && $array[$i] !== "&" ) {
				$err_msg	= "演算子ではない文字が入力されています。<br>なお、文字列を入力する場合は\"\"等で囲んでください。";
				$err_flug	= true;
				return;
			}

			// 文字列配列の１文字目が演算子の場合（符号と思われる場合）
			if($i == 0 && $data_count	== 0){
				switch($array[$i]){
					case "+" :
						$sign = "+";
						break;
					case "-" :
						$sign = "-";
						break;
					default:
						// 上記以外の演算子が式の始めに存在するのはおかしいのでエラー
						$err_msg	= "1文字目の演算子に誤りがあります（符号は「+」or「-」を入力して下さい）";
						$err_flug	= true;
						return;
						break;
				}
				continue;
			}

			// 符号がある場合は、符号が付くデータ配列の添え字と同じ添え字に該当符号を記憶する（べき乗計算で使う）
			!empty($sign) ? $sign_data_no[$data_count] = $sign : "";

			// 文字が演算子に移った時点で符号内容を初期化。
			$sign = "";

			// 文字が演算子に移った時点で小数判定フラグを初期化。
			$decimal_flug = false;

			// 演算子文字が連続していない場合（1文字目の場合）
			if($next_operator_no == 0 || $i != $next_operator_no ){
				// 演算子文字が1文字目の場合（演算子配列の1要素に格納）
				$operator_array[$operator_count]	= $array[$i];
				$next_operator_no	= $i + 1 ;

				// 元々の文字列がデータ文字 → 演算子文字に変わった為、データ配列の添え字を加算
				if( !empty($data_array) ){
					$data_count++;
				}
				// この回（ループ内）で必要な処理は終了したのでのでContinue
				continue;
			}

			// 演算子文字が連続している場合（2文字目以降の場合）
			// 注意：ここの処理に来る文字には「(」、「)」、「"」、「'」は含まれない
			if($i == $next_operator_no ){

				// 符号が含まれる計算の時（直前の演算子文字が「！」ではなく、現在の演算子文字が「＋」Or「－」の時）
				// 例：「５* －５」とか「５＋－５」とか。
				// 例：直前の演算子が「！」の場合、「５！－３」のようにマイナスが符号ではなく演算子になるので除外。
				if($array[$i-1] !== "!" && ( $array[$i] === "+" || $array[$i] === "-" ) ){
					// 当該文字は演算子ではなく「符号」として扱う
					$sign	= $array[$i];
					// データ配列の添え字数を重複して加算してしまうのでContinue
					continue;
				}

				// 直前の演算子文字が「！」の時
				if($array[$i-1] === "!" ){
					// 階乗が含まれる計算の時パターン１（直前の演算子文字が「！」で、現在の演算子文字が「＝」ではない時）
					// 例：「２！- ５」とか。「！＝」は比較演算子になるので除外。
					if($array[$i] !== "=" ){
						//「！」以降、演算子が連続したとしても別グループの演算子として取り扱う
						$operator_count++;
						$operator_array[$operator_count]	= $array[$i];
						// データ配列の添え字数を重複して加算してしまうのでContinue
						continue;
					}

					// 階乗が含まれる計算の時パターン２（直前の演算子文字が「！」で、現在と次の演算子文字が「＝」の時）
					// 例：「２！== ５」とか。
					if($array[$i] === "=" && $array[$i+1] === "=" ){
						//「！」以降、演算子が連続したとしても別グループの演算子として取り扱う
						$operator_count++;
						$operator_array[$operator_count]	= $array[$i];
						// 演算子配列が続く可能性があるので次の演算子番号を加算
						$next_operator_no	= $i + 1 ;
						// データ配列の添え字数を重複して加算してしまうのでContinue
						continue;
					}
					// 上記以外の場合は比較演算子「!=」になるはずなので以降で処理
				}

				// 上記の何れでもない場合は比較演算子になるので形をチェックして処理
				if($array[$i-1] === ">" && $array[$i] === "=" || 
					 $array[$i-1] === "<" && $array[$i] === "=" || 
					 $array[$i-1] === "=" && $array[$i] === "=" ||
					 $array[$i-1] === "!" && $array[$i] === "=" ||
					 $array[$i-1] === "|" && $array[$i] === "|" ||
					 $array[$i-1] === "&" && $array[$i] === "&" ){
					// 文字列連結して代入
					$operator_array[$operator_count] = $operator_array[$operator_count] . $array[$i];
					// 演算子配列が続く可能性があるので次の演算子番号を加算
					$next_operator_no	= $i + 1 ;
					// データ配列の添え字数を重複して加算してしまうのでContinue
					continue;
				}

				// 更に上記の何れでもない場合はエラー扱い
				$err_msg = "予期せぬエラー（演算子ではない文字が入力されています）";
				$err_flug = true;
				return;

			}
			
		}
		// echo "i:$i /// 文字:$array[$i] /// 符号:$sign";
		// pre_dump($data_array);
		// pre_dump($operator_array);
		// echo "<hr>";
	}

	if( empty($data_array) && empty($operator_array) ){
		$err_msg = "（）内に式が入力されていません。";
		$err_flug = true;
		return;
	}

	// echo $sign;
	// pre_dump($data_array);
	// pre_dump($operator_array);

	// 検証過程の表示用
	if($display_calc_process_flug){
		echo "データ配列";
		pre_dump($data_array);
		echo "演算子配列";
		pre_dump($operator_array);
	}

	// データ配列と演算子配列を使用した計算を行って返す
	return calc($data_array, $operator_array, $sign_data_no);

}

//【関数】引数のデータ配列と演算子配列から各種演算を優先順位規則に従って処理する。
// 引数：データ配列、演算子配列
// 返値：計算結果（Int/Float/Boolean）
// 備考：データ配列[10][1][45]、演算子配列[+][*]をもとに演算子配列の優先順位が高い順に計算
// 　　　優先順位１：べき乗「^」、階乗「!」
// 　　　優先順位２：除算「/」、乗算「*」、剰余算「%」
// 　　　優先順位３：加算「+」、減算「-」
// 　　　優先順位４：大なり「>」、大なりイコール「>=」、小なり「<」、小なりイコール「<=」
// 　　　優先順位５：等号「==」、不等号「!=」
// 　　　優先順位６：AND「&&」
// 　　　優先順位７：OR「||」
// 処理：演算子配列をループして優先順位上位の演算子を検索、該当の演算子があれば演算処理実施
// 　　　処理後は使用した演算子を配列から削除、演算結果をデータ配列に上書き、引数が２つある場合２つ目のデータは配列から削除
// 　　　処理実施の有無に関わらずデータ配列と演算子配列を詰める。
function calc($data_array, $operator_array, $sign_data_no){
	global $err_flug, $err_msg, $display_calc_process_flug;

	//【優先順位１】べき乗、階乗の処理
	// 演算子配列の要素数分ループ処理をして演算子の中身を判定、該当の演算子があれば処理（複数ある場合は出現順に処理）
	// 演算子配列は毎々削除される為、ループ回数は別に記憶する
	$max_count = count($operator_array);
	$data_subscript_flug = false;
	for($i = 0 ; $i < $max_count ; $i++){
		switch($operator_array[$i]){
			// べき乗の計算
			case "^":
				$exponentiation_flug = false;
				if(!$data_subscript_flug){
					if(isset($data_array[$i]) && isset($data_array[$i+1]) && 
						 ( is_numeric($data_array[$i])		||	is_bool($data_array[$i])		) && 
						 ( is_numeric($data_array[$i+1])	||	is_bool($data_array[$i+1])	)	){
						$exponentiation_flug			= true;
						$exponentiation_subscript	= $i;
					}
				}else{
					if(isset($data_array[$i-1]) && isset($data_array[$i]) && 
						 ( is_numeric($data_array[$i-1])	||	is_bool($data_array[$i-1])	) && 
						 ( is_numeric($data_array[$i])		||	is_bool($data_array[$i])		)	){
						$exponentiation_flug			= true;
						$exponentiation_subscript	= $i - 1 ;
					}
				}
				
				if($exponentiation_flug){
					// 底の取得
					// 底が（）なしのマイナスの場合は、いったん正の数に戻して計算後、計算結果をマイナスにする必要があるのでフラグを設定。
					// 例１： -2^2 		=	2 × 2 × -1 = -4 
					// 例２： (-2)^2 	=	(-2) × (-2) = 4 になるので計算方法が変わる。
					$minus_flug = false;
					if(isset($sign_data_no[$exponentiation_subscript]) && $sign_data_no[$exponentiation_subscript] == "-"){
						$base = $data_array[$exponentiation_subscript] * -1 ;
						// 後でマイナスに変換する必要がある
						$minus_flug = true;
					}else{
						$base	= $data_array[$exponentiation_subscript];
					}
					// 指数の取得
					$exponent								= $data_array[$exponentiation_subscript + 1];
					$exponentiation_result	= 1;
					// 指数が正負でも計算方法が変わるので切り分け。
					switch(true){
						// 指数が正の数の時
						case $exponent > 0:
							for($j = 0 ; $j < $exponent ; $j++){
								$exponentiation_result *= $base;
							}
							break;
						// 指数が0の時
						case $exponent == 0:
							$exponentiation_result = 1;
							break;
						// 指数が負の数の時
						case $exponent < 0:
							$exponent *= -1;
							for($j = 0 ; $j < $exponent ; $j++){
								$exponentiation_result /= $base;
							}
							break;
					}
					// 底の符号状態に合わせて計算結果をマイナスに変える。
					if($minus_flug){
						$data_array[$exponentiation_subscript+1] = $exponentiation_result * -1;
					}else{
						$data_array[$exponentiation_subscript+1] = $exponentiation_result;
					}
					unset($data_array[$exponentiation_subscript]);
					unset($operator_array[$i]);
				}else{
					$err_msg	= "「 $operator_array[$i] 」の前後に数値が入力されていません。";
					$err_flug	= true ;
					return;
				}
				break;

			// 階乗の計算
			case "!":
				$kaijyo_flug	= false;
				if(!$data_subscript_flug){
					if( isset($data_array[$i]) && ( is_numeric($data_array[$i]) || is_bool($data_array[$i]) ) ){
						$data_subscript_flug	= true;
						$kaijyo_flug					= true;
						$kaijyo_subscript			= $i;
					}
				}else{
					if( isset($data_array[$i-1]) && is_numeric($data_array[$i-1]) ){
						$data_subscript_flug	= true;
						$kaijyo_flug					= true;
						$kaijyo_subscript			= $i - 1;
					}
				}
				if($kaijyo_flug){
					// ベース数値の取得
					$base						= $data_array[$kaijyo_subscript];
					$kaijyo_result	= 1;
					switch(true){
						// ベース数値が正の数の時
						case $base > 0:
							for($j = 1 ; $j <= $base ; $j++){
								$kaijyo_result *= $j;
							}
							break;
						// ベース数値が0の時
						case $base == 0:
							$kaijyo_result = 0;
							break;
						// ベース数値が負の数の時（階乗は非負整数のみを対象にした計算の為エラー扱い）
						case $base < 0:
							$err_msg = "階乗の計算において、負の値が指定されています（負の値の階乗は計算できません）";
							$err_flug = true;
							return;
							break;
					}
					$data_array[$kaijyo_subscript] = $kaijyo_result; 
					// 階乗の場合は削除するべき不要なデータがないのでデータ配列は削除しない
					unset($operator_array[$i]);
				}else{
					$err_msg	= "「 $operator_array[$i] 」の前に数値が入力されていません。";
					$err_flug	= true ;
					return;
				}
				break;
		}
	}
	// 何かしらの演算処理があった場合は、データ配列と演算子配列を詰める
	$data_array			= alignment_array($data_array);
	$operator_array	= alignment_array($operator_array);
	// pre_dump($data_array);
	// pre_dump($operator_array);


	//【優先順位２】四則演算の除算、乗算、剰余算の計算
	// 演算子配列の要素数分ループ処理をして演算子の中身を判定、該当の演算子があれば処理（複数ある場合は出現順に処理）
	// 演算子配列は毎々削除される為、ループ回数は別に記憶する
	$max_count = count($operator_array);
	for($i = 0 ; $i < $max_count ; $i++){
		switch($operator_array[$i]){
			case "/":
				if(isset($data_array[$i]) && isset($data_array[$i+1]) && 
					( is_numeric($data_array[$i])		||	is_bool($data_array[$i])		) && 
					( is_numeric($data_array[$i+1])	||	is_bool($data_array[$i+1])	)	){
					$data_array[$i+1] = $data_array[$i] / $data_array[$i+1];
					unset($data_array[$i]);
					unset($operator_array[$i]);
				}else{
					$err_msg	= "「 $operator_array[$i] 」の前後に数値が入力されていません。";
					$err_flug	= true ;
					return;
				}
				break;
			case "*":
				if(isset($data_array[$i]) && isset($data_array[$i+1]) && 
					( is_numeric($data_array[$i])		||	is_bool($data_array[$i])		) && 
					( is_numeric($data_array[$i+1])	||	is_bool($data_array[$i+1])	)	){
					$data_array[$i+1] = $data_array[$i] * $data_array[$i+1];
					unset($data_array[$i]);
					unset($operator_array[$i]);
				}else{
					$err_msg	= "「 $operator_array[$i] 」の前後に数値が入力されていません。";
					$err_flug	= true ;
					return;
				}
				break;
			case "%":
				if(isset($data_array[$i]) && isset($data_array[$i+1]) && 
					( is_numeric($data_array[$i])		||	is_bool($data_array[$i])		) && 
					( is_numeric($data_array[$i+1])	||	is_bool($data_array[$i+1])	)	){
					$data_array[$i+1] = $data_array[$i] % $data_array[$i+1];
					unset($data_array[$i]);
					unset($operator_array[$i]);
				}else{
					$err_msg	= "「 $operator_array[$i] 」の前後に数値が入力されていません。";
					$err_flug	= true ;
					return;
				}
				break;
		}
	}
	$data_array			= alignment_array($data_array);
	$operator_array	= alignment_array($operator_array);
	// pre_dump($data_array);
	// pre_dump($operator_array);


	//【優先順位３】四則演算の加算、減算の計算
	// 演算子配列の要素数分ループ処理をして演算子の中身を判定、該当の演算子があれば処理（複数ある場合は出現順に処理）
	// 演算子配列は毎々削除される為、ループ回数は別に記憶する
	$max_count = count($operator_array);
	for($i = 0 ; $i < $max_count ; $i++){
		switch($operator_array[$i]){
			case "+":
				if( isset($data_array[$i]) && isset($data_array[$i+1]) && 
						( is_numeric($data_array[$i])		||	is_bool($data_array[$i])		) && 
						( is_numeric($data_array[$i+1])	||	is_bool($data_array[$i+1])	)	){
					$data_array[$i+1] = $data_array[$i] + $data_array[$i+1];
					unset($data_array[$i]);
					unset($operator_array[$i]);
				}else{
					$err_msg	= "「 $operator_array[$i] 」の前後に数値が入力されていません。";
					$err_flug	= true ;
					return;
				}
				break;
			case "-":
				if(isset($data_array[$i]) && isset($data_array[$i+1]) && 
					( is_numeric($data_array[$i])		||	is_bool($data_array[$i])		) && 
					( is_numeric($data_array[$i+1])	||	is_bool($data_array[$i+1])	)	){
					$data_array[$i+1] = $data_array[$i] - $data_array[$i+1];
					unset($data_array[$i]);
					unset($operator_array[$i]);
					break;
				}else{
					$err_msg	= "「 $operator_array[$i] 」の前後に数値が入力されていません。";
					$err_flug	= true ;
					return;
				}
		}
	}
	$data_array			= alignment_array($data_array);
	$operator_array	= alignment_array($operator_array);
	// pre_dump($data_array);
	// pre_dump($operator_array);


	//【優先順位４】比較文の大小比較を処理
	// 演算子配列の要素数分ループ処理をして演算子の中身を判定、該当の演算子があれば処理（複数ある場合は出現順に処理）
	// 演算子配列は毎々削除される為、ループ回数は別に記憶する
	$max_count = count($operator_array);
	for($i = 0 ; $i < $max_count ; $i++){
		switch($operator_array[$i]){
			case "<":
				if( isset($data_array[$i]) && isset($data_array[$i+1]) ){
					if(convert_utf_8_hex($data_array[$i]) < convert_utf_8_hex($data_array[$i+1]) ){
						$data_array[$i+1] = true;
					}else{
						$data_array[$i+1] = false;
					}
					unset($data_array[$i]);
					unset($operator_array[$i]);
				}else{
					$err_msg	= "「 $operator_array[$i] 」の前後にデータが入力されていません。";
					$err_flug	= true ;
					return;
				}
				break;
			case "<=":
				if( isset($data_array[$i]) && isset($data_array[$i+1]) ){
					if(convert_utf_8_hex($data_array[$i]) <= convert_utf_8_hex($data_array[$i+1]) ){
						$data_array[$i+1] = true;
					}else{
						$data_array[$i+1] = false;
					}
					unset($data_array[$i]);
					unset($operator_array[$i]);
				}else{
					$err_msg	= "「 $operator_array[$i] 」の前後にデータが入力されていません。";
					$err_flug	= true ;
					return;
				}
				break;
			case ">":
				if( isset($data_array[$i]) && isset($data_array[$i+1]) ){
					if(convert_utf_8_hex($data_array[$i]) > convert_utf_8_hex($data_array[$i+1]) ){
						$data_array[$i+1] = true;
					}else{
						$data_array[$i+1] = false;
					}
					unset($data_array[$i]);
					unset($operator_array[$i]);
				}else{
					$err_msg	= "「 $operator_array[$i] 」の前後にデータが入力されていません。";
					$err_flug	= true ;
					return;
				}
				break;
			case ">=":
				if( isset($data_array[$i]) && isset($data_array[$i+1]) ){
					if(convert_utf_8_hex($data_array[$i]) >= convert_utf_8_hex($data_array[$i+1]) ){
						$data_array[$i+1] = true;
					}else{
						$data_array[$i+1] = false;
					}
					unset($data_array[$i]);
					unset($operator_array[$i]);
				}else{
					$err_msg	= "「 $operator_array[$i] 」の前後にデータが入力されていません。";
					$err_flug	= true ;
					return;
				}
				break;
		}
	}
	$data_array			= alignment_array($data_array);
	$operator_array	= alignment_array($operator_array);
	// pre_dump($data_array);
	// pre_dump($operator_array);


	//【優先順位５】比較文の等号、不等号を処理
	// 演算子配列の要素数分ループ処理をして演算子の中身を判定、該当の演算子があれば処理（複数ある場合は出現順に処理）
	// 演算子配列は毎々削除される為、ループ回数は別に記憶する
	$max_count = count($operator_array);
	for($i = 0 ; $i < $max_count ; $i++){
		switch($operator_array[$i]){
			case "==":
				if( isset($data_array[$i]) && isset($data_array[$i+1]) ){ 
					if(convert_utf_8_hex($data_array[$i]) == convert_utf_8_hex($data_array[$i+1]) ){
						$data_array[$i+1] = true;
					}else{
						$data_array[$i+1] = false;
					}
					unset($data_array[$i]);
					unset($operator_array[$i]);
				}else{
					$err_msg	= "「 $operator_array[$i] 」の前後にデータが入力されていません。";
					$err_flug	= true ;
					return;
				}
				break;
			case "!=":
				if( isset($data_array[$i]) && isset($data_array[$i+1]) ){ 
					if(convert_utf_8_hex($data_array[$i]) != convert_utf_8_hex($data_array[$i+1]) ){
						$data_array[$i+1] = true;
					}else{
						$data_array[$i+1] = false;
					}
					unset($data_array[$i]);
					unset($operator_array[$i]);
				}else{
					$err_msg	= "「 $operator_array[$i] 」の前後にデータが入力されていません。";
					$err_flug	= true ;
					return;
				}
				break;
		}
	}
	$data_array			= alignment_array($data_array);
	$operator_array	= alignment_array($operator_array);
	// pre_dump($data_array);
	// pre_dump($operator_array);

	//【優先順位６】比較文のANDを処理
	// 演算子配列の要素数分ループ処理をして演算子の中身を判定、該当の演算子があれば処理（複数ある場合は出現順に処理）
	// 演算子配列は毎々削除される為、ループ回数は別に記憶する
	$max_count = count($operator_array);
	for($i = 0 ; $i < $max_count ; $i++){
		switch($operator_array[$i]){
			case "&&":
				if( isset($data_array[$i]) && isset($data_array[$i+1]) ){ 
					if($data_array[$i] && $data_array[$i+1]){
						$data_array[$i+1] = true;
					}else{
						$data_array[$i+1] = false;
					}
					unset($data_array[$i]);
					unset($operator_array[$i]);
				}else{
					$err_msg	= "「 $operator_array[$i] 」の前後にデータが入力されていません。";
					$err_flug	= true ;
					return;
				}
				break;
		}
	}
	$data_array			= alignment_array($data_array);
	$operator_array	= alignment_array($operator_array);
	// pre_dump($data_array);
	// pre_dump($operator_array);

	//【優先順位７】比較文のORを処理
	// 演算子配列の要素数分ループ処理をして演算子の中身を判定、該当の演算子があれば処理（複数ある場合は出現順に処理）
	// 演算子配列は毎々削除される為、ループ回数は別に記憶する
	$max_count = count($operator_array);
	for($i = 0 ; $i < $max_count ; $i++){
		switch($operator_array[$i]){
			case "||":
				if( isset($data_array[$i]) && isset($data_array[$i+1]) ){ 
					if($data_array[$i] || $data_array[$i+1]){
						$data_array[$i+1] = true;
					}else{
						$data_array[$i+1] = false;
					}
					unset($data_array[$i]);
					unset($operator_array[$i]);
				}else{
					$err_msg	= "「 $operator_array[$i] 」の前後にデータが入力されていません。";
					$err_flug	= true ;
					return;
				}
				break;
		}
	}
	$data_array			= alignment_array($data_array);
	$operator_array	= alignment_array($operator_array);


	// pre_dump($data_array);
	// pre_dump($operator_array);

	// 検証過程の表示用
	if($display_calc_process_flug){
		echo "<br>計算結果";
		pre_dump($data_array);
		echo "<hr>";
	}

	return get_result($data_array);

}

//【関数】計算結果を配列から取得する
// 引数：計算結果配列
// 返値：計算結果（Int/Float/Boolean）を配列ではない普通の値として返す
function get_result($array){
	foreach($array as $val){
		// 重要：計算結果が「0」の場合は!=""がFalseになるので厳密な比較が必要
		if(is_bool($val) || $val !== "" ){
			return $val;
		}
	}
}

// ＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝　以降、各種処理　＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝


if($_SERVER["REQUEST_METHOD"] == "POST"){

	isset($_POST["check_proccess"]) ? $display_calc_process_flug = true : "" ;

	// POST送信されたデータの取得
	$calc_string = $_POST["calc_string"];

	// 入力された文字列の中でmb_convert_kana()で半角変換できない文字等を適切な半角文字に変換する。
	// 入力された文字列の中で全角日本語（SJIS）をUTF-8に変換する。
	// 関数の備考を見ればわかるけどstr_split()を使うとSJIS → UTF-8はうまく変換できなくなる。
	$calc_string = calc_escape($calc_string);

	// 入力された文字列の英数字とスペースを半角に強制変換
	$calc_string = mb_convert_kana($calc_string, "as");

	// 半角スペースを削除
	$calc_string = str_replace(" ", "", $calc_string);

	// 最初が「=」の場合は最初の「=」を削除
	substr($calc_string, 0, 1) == "=" ? $calc_string = substr($calc_string, 1) : "" ;

	// 最後が「=」の場合は最後の「=」を削除
	mb_substr($calc_string, -1) == "=" ? $calc_string = substr($calc_string, 0, -1) : "" ;

	// 文字を1文字ずつ取り出して配列に格納
	$calc_string_array = str_split($calc_string);

	// 最低限のバリデーションチェックを行う
	if( valid($calc_string_array) ){

		// 計算を行う
		$result = calc_priority($calc_string_array, 0);

		// 結果がFloat型かつ小数の場合は小数点第4位を四捨五入した値と分数を表示する。
		if(is_float($result) && preg_match('/^([1-9]\d*|0)\.(\d+)?$/', $result)){
			$display_result = round($result, 4) ;
			$float_flug = true;
		}else{
			$display_result = $result;
		}

		// 結果がboolean型の値を文字列に変換する（処理しないと0か1に変換されてしまう）
		if(is_bool($result)){
			$result ? $display_result ="真 (true)" : $display_result ="偽 (false)" ; 
		}

	}

	$err_flug ? $display_result = "計算不能" : "";

	// 入力された式をHTMLでの再表示用にクオーテーションをエスケープ演算子に置き換える
	$redisplay_value = redisplay_escape($_POST["calc_string"]);

}


// ＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝　以降、画面表示　＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝


?>

<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta charset="utf-8">
	<title>式の判定と計算</title>
	<style>
		html {
			scroll-behavior: smooth;
		}

		/* エラー表記 */
		.err,
		.attention{
			color : red;
		}

		/* メインコンテンツ */
		.calc_content{
			margin-right	: 80px;
			margin-bottom	: 50px;
		}
		.calc_string{
			height : 40px;
			padding-left : 0.5em;
			font-size : 1.2em;
		}
		.calc_result{
			height 				: 30px;
			width					: 200px;
			padding				: 10px;
			border-radius : 10px;
			background		: aqua;
			font-size			: 1.2em;
		}
		.submit_btn{	
			height				: 50px;
			width					: 100px;
			/* border				: 1px solid orange;
			border-radius : 10px;
			background		: orange; */
			font-size			: 1.1em;
			/* color					: white; */
		}
		.check_proccess{
			margin-left : 20px;
		}
		.check_proccess_btn{
			height	: 45px;
		}

		/* 入力例テーブル */
		.ex_table,
		.err_case_table{
			margin-right		: 60px;
			margin-bottom		: 80px;
			border					: solid 1px;
			border-collapse	: collapse;
		}
		.ex_table th,
		.ex_table td,
		.err_case_table th,
		.err_case_table td{
			border					: solid 1px;
			text-align			: center;
			padding					: 5px 10px;
		}
		.ex_table th,
		.err_case_table th{
			background 			: lightgray;
		}
		.ex_table td:nth-of-type(2),
		.err_case_table td:nth-of-type(2){
			text-align			: left;
		}

		/* フロート処理 */
		.float_left{
			float : left;
		}
		.float_right{
			float : right;
		}
		.float_end{
			display	: block;
			content	: "";
			clear		: both;
		}

		/* フッター */
		.footer{
			margin-top				: 100px;
			padding						: 10px 20px;
			background-color	: #5ab4bd;
			color							: white;
			text-align				: center;
		}
	</style>
	<!-- FontAwesomeの読み込み -->
	<link href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" rel="stylesheet">
	<script>
		window.onload = function(){
			var data = document.getElementById("hidden_result").value;
			if(data !== ""){
				convert_fraction(data);
			}
		}

		// テキストコピー（ボツ）
		function copy(id) {
			const copy_text_tag	= document.getElementById(id);
			const input_tag			= document.createElement("input");
			input_tag.value			= copy_text_tag.innerText;
			copy_text_tag.appendChild(input_tag);
			input_tag.select();
			document.execCommand("copy");
			copy_text_tag.removeChild(input_tag);
		}
		// テキストコピー＆入力タグに入力（入力例をコピペすんのめんどくさいから作った）
		function copy_paste(id) {
			const copy_text = document.getElementById(id).innerText;
			const input_tag	= document.getElementById("calc_string");
			input_tag.value = copy_text;
			document.getElementById("hidden_result").value = "";
			document.getElementById("result").innerText = "";

			const top_tag	= document.getElementById("top");
			top_tag.scrollIntoView({behavior:'smooth',block:'start'});
		}

		// 小数点 → 分数に変換
		function convert_fraction(data){
			// 値の設定
			var maxDenominator	= 10000;		// +prompt("分母の上限 =", "10000");
			var errorCapacity		= 0.000001;	// +prompt("誤差の許容範囲 =   (絶対値)", "0.000001");

			// 分母 i を変えながら、data に近い分数を探していく
			for(var i = 1 ; i < maxDenominator ; i++) {
					// 分母 i のとき、分数の値が data に最も近くなり得る分子
					var j1 = (data * i) | 0 ;			// data より小さい側
					var j2 = j1 + 1;							// data より大きい側
					
					// 分数の浮動小数点数による近似値を計算
					var v1 = j1 / i;		// data より小さい側
					var v2 = j2 / i;		// data より大きい側

					// 誤差が許容範囲内であれば、結果に i/j を出力して終了
					if (Math.abs(v1 - data) < errorCapacity) {
						document.getElementById("molecule").textContent			= j1;
						document.getElementById("denominator").textContent	= i;
						// alert("結果 = " + j1 + " / " + i + " ( = " + v1 + " )");
						return;
					}
					if (Math.abs(v2 - data) < errorCapacity) {
						document.getElementById("molecule").textContent			= j2;
						document.getElementById("denominator").textContent	= i;
						// alert("結果 = " + j2 + " / " + i + " ( = " + v2 + " )");
						return;
					}
			}
			// 結果があれば既にreturnしているはずなので、ここが実行される場合は結果なし
			document.getElementById("mfrac").innerText = "（分数表示不可）";
			// alert("結果 = 該当なし");
			return;
		}

		// 計算過程も表示するボタンの処理
		function check_process(form_id){
			var input_string = document.getElementById("calc_string").value;
			document.getElementById("hidden_calc_string").value = input_string;
			document.getElementById(form_id).submit();
		}
	</script>
	<!-- 分数表記する為に必要なJSライブラリ -->
	<script async src="https://cdnjs.cloudflare.com/ajax/libs/mathjax/2.7.2/MathJax.js?config=TeX-MML-AM_CHTML"></script>
</head>
<body>
	<h1 id="top" >式の計算と判定</h1>
	<section class="calc_content float_left">
		<font>
			わかる人にはわかる、結構複雑な処理が必要なシステム。<br>
			ちなみに入力例の欄はFloat処理してるので横長画面なりサイズ変更して見ると見やすくなるヨ。
		</font>
		<h2>式の評価</h2>
		<section class="err">
			<?= $err_msg ?>
		</section>
		<section>
			<form action="<?= $source_name ?>" method="POST">
				<input type="text" id="calc_string" name="calc_string" class="calc_string" value="<?= $redisplay_value ?>" size="50" placeholder="式を入力して下さい">
				<input type="submit" class="submit_btn" value="送信する">
			</form>
		</section>
		<br>
		<section class="calc_result float_left">
			結果：<span id="result">
			<?php
				echo $display_result;
				if($float_flug){
					echo " <span id=\"mfrac\">( <math><mfrac><mn id=\"molecule\"></mn><mn id=\"denominator\"></mn></mfrac></math> )</span>";
				}
			?>
			</span>
			<input type="hidden" id="hidden_result" value="<?= $result ?>" >
		</section>
		<section class="check_proccess float_left">
			<form id="check_proccess_form" action="<?= $source_name ?>" method="POST">
				<input type="hidden" id="hidden_calc_string" name="calc_string" value="<?= $redisplay_value ?>" >
				<input type="hidden" name="check_proccess" value="true" >
				<!-- <input type="submit" id="check_proccess_btn" class="check_proccess_btn" value="計算過程も見る" onclick="check_process('check_proccess_form')"> -->
			</form>
		</section>
		<div class="float_end"></div>
		<br>

		<h2>処理できる事</h2>
		<section>
			<ul>
				<li>四則演算　　（「+」,「-」,「*」,「/」（「×」,「÷」等の文字入力も可））</li>
				<li>その他の演算（ 剰余算「%」、階乗「!」、べき乗「^」）</li>
				<li>比較文１　　（「>」,「>=」,「<」,「<=」による比較文）</li>
				<li>比較文２　　（「==」,「!=」,「||」,「&&」による比較文）</li>
				<li>符号認識　　（「+」,「-」といった正負符号の認識）</li>
				<li>小数認識　　（「1.2」,「0.1234」といた小数の認識）</li>
				<li>文字列認識　（「""」,「''」の認識）</li>
				<li>（）による優先順位の判定</li>
				<li>四則演算等と比較文の混在演算（true = 1、false = 0 扱い）</li>
				<li>結果が小数の場合は分数値も表示</li>
				<li>結果が真偽の場合は真 (true) / 偽 (false)で表示</li>
				<li>全角/半角文字、スペース、「＝」の混在入力</li>
			</ul>
			<br>
			<span class="attention">
				※ 注意 ※<br>
				・コンテンツはFloat処理してるので横長画面 or 画面縮小率を下げると見やすくなります。<br>
				・演算子同士の優先順位はプログラミング言語の一般共通的な優先順位に準拠。<br>
				（ざっくり言うと 算術演算子 > 比較演算子 の順番で処理）<br>
				・文字列を比較する時はUTF8にエンコードしてから16進ダンプで比較。<br>
			</span>
		</section>
	</section>
	<section class="float_left">
		<h2>入力例</h2>
		<table class="ex_table">
			<tr><th>No</th><th>式</th><th>答え</th><th>コピペ</th><tr>
			<?php 
				for($i = 0 ; $i < count($ex) ; $i++){
					$no = $i + 1;
					echo "<tr><td>{$no}</td><td id=\"ex{$no}\">" , $ex[$i]["ex"] , "</td>
										<td>" , $ex[$i]["answer"] , "</td>
										<td><button onclick=\"copy_paste('ex{$no}')\">入力欄にコピー</button>
								</tr>";
				}
			?>
		</table>
	</section>
	<section class="float_left">
		<h2>エラーテスト例</h2>
		<table class="err_case_table">
			<tr><th>No</th><th>式</th><th>答え</th><th>コピペ</th><tr>
			<?php 
				for($i = 0 ; $i < count($err_case) ; $i++){
					$no = $i + 1;
					echo "<tr><td>{$no}</td><td id=\"err_case_{$no}\">" , $err_case[$i]["err"] , "</td>
										<td>計算不能</td>
										<td><button onclick=\"copy_paste('err_case_{$no}')\">入力欄にコピー</button>
								</tr>";
				}
			?>
		</table>
	</section>
	<div class="float_end"></div>

	<!-- footer -->
	<footer class="footer" itemscope itemtype="http://schema.org/Person">
		<p>お問い合わせは
			<a href="mailto:hiroaki.akino@gmail.com?subject=お問い合わせ&amp;body=----------------------------------------%0D%0Aお名前：%0D%0A----------------------------------------%0D%0A 以降にお問い合わせ内容を記載下さい。">
				コチラ
			</a>
		</p>
		<i class="far fa-copyright"></i>
		<small> 2020 <a href="https://www.g096407.shop/hiroaki-akino/self_introduction.html"> Hiroaki Akino</a></small>
	</footer>
	</body>
</html>