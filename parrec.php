<?php

$replace = [
	'input' => '<label>{input1}<input name="{input2}"></label>',
	'span'  => '<span style="color: #000; background-color: rgba(0,255,0,0.4)">{infinite}</span>',
	'div'   => '<div>{infinite}</div>',
	'column' => '<div style="display: flex; flex-direction: column; max-width: 600px; margin: 0 auto">{infinite}</div>',
	'pre'   => '<pre>{infinite}</pre>',
	'row'   => '<div style="display: flex; flex-direction: row; flex-wrap: nowrap; background-color: #ccc; border-radius: 10px; padding: 1em;margin: .5em">{infinite}</div>'
];

class Parser
{
	private string $originalText;
	private array $originalTextArr;
	private array $replace;
	private array $parPositions;
	private int $pos = 0;
	private Replacer $replacer;

	public function __construct(string $originalText, array $replace)
	{
		$this->originalText = $originalText;
		$this->originalTextArr = preg_split('//u', $originalText, -1, PREG_SPLIT_NO_EMPTY);
		$this->replace = $replace;
		$this->replacer = new Replacer();
	}

	public function generateText(): array
	{
		echo 'Original text: <br><pre style="background-color: rgba(7,79,178,0.3)">' . $this->originalText . '</pre><br><br>';

		$tree = $this->makeSemanticTree();

		Printer::prettyPrint($tree);

//		Printer::prettyPrint($this->parPositions);
//		Printer::prettyPrint($tree);

//		echo 'The tree: <br>';
//		echo '<div style="background-color: rgba(18,105,223,0.38); color: #000; padding: 10px; border-radius: 10px">'
//			. mb_substr(Printer::treeAsString($tree), 0, -45)
//			. '</div>';

		echo '<br> The result: <br><span style="background-color: rgba(71,197,21,0.42)">'
			. $this->replaceInputs($tree)
			. '</span><br><br>';

		return $tree;
	}

	public function checkParenthesis(string $text): array
	{
		$i = 0;
		$txt = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
		$char = $txt[$i];
		$stack = [];

		$this->parPositions = [];

		while ($char)
		{
			if ($char === '(') {
				array_push($stack, $i);
				$this->parPositions[$i] = 0;
			} elseif ($char === ')') {
				if (empty($stack)) {
					throw new \Exception("Скобки неверно расставлены (символ $i - лишняя закрывающая скобка)");
				}

				$this->parPositions[array_pop($stack)] = $i;
			}

			$i++;
			$char = $txt[$i];
		}

		if (!empty($stack)) {
			throw new \Exception("Скобки неверно расставлены (лишняя открывающая скобка)");
		}

		return $this->parPositions;
	}

	private function replaceInputs($tree)
	{
		$replaces = [];
		foreach ($tree as $index => $node) {
			$replaces[] = $this->replaceNested($tree);
			unset($tree[$index]);
		}

		return implode(' ', $replaces);
	}

	private function replaceNested($tree)
	{
		foreach ($tree as $index => $node) {
			foreach ($node as $depth => $value) {
				if (is_array($value['content']) && !empty($value['content'])) {
					$replaces = [];

					foreach ($value['content'] as $i => $v) {
						$replaces[] = $this->replaceNested($tree[$index][$depth]['content']);
						unset ($tree[$index][$depth]['content'][$i]);
					}

					return $this->replacer->replaceArguments(
						$this->replace[$value['args']], $replaces
					);
				}

				if ($value['args'][0] === '"' && $value['args'][-1] === '"') {
					return $value['args'];
				}

				return $this->replacer->replaceArguments(
					$this->replace[$value['args']], $value['content']
				);
			}
		}

		return $tree;
	}

	private function makeSemanticTree(): array
	{
		$this->checkParenthesis($this->originalText);

		return $this->makeContent($this->originalText, 0);
	}

	private function recPar(string $text, int $depth = 0): array
	{
		if ($depth > 10) {
			return [];
		}
		$depth++;

		$word = '';

		$i = 0;
		$txt = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
		$char = $txt[$i];

		$this->reposIfNeeded($text);

		while ($char)
		{
			$this->checkPos($char, $txt, $i);

			if ($char === '(') {
				$len = $this->parPositions[$this->pos] - $this->pos;

				return [
					$depth => [
						'args' => trim($word),
						'content' => $this->makeContent(
							mb_substr($this->originalText, ++$this->pos, $len),
							$depth
						)
					]
				];
			} elseif ($char !== ')') {
				$word .= $char;
			}

			$i++;
			$this->pos++;
			$char = $txt[$i];
		}

		return [
			$depth => [
				'args' => trim($word),
				'content' => [],
			],
		];
	}

	private function reposIfNeeded($text): void
	{
		if ($text === mb_substr($this->originalText, $this->pos, mb_strlen($text))){
			return;
		}

//		echo "<span style='background-color:rgba(255,255,0,0.85)'>Что-то не так $text<br>";
		$tmp = mb_stripos($this->originalText, $text);

		if ($tmp !== false) {
			$this->pos = $tmp;
//			echo "Позиция была: {$this->pos}";
//			echo " стала: {$this->pos}";
		} //else {
//			Printer::prettyPrint(['orig' => $this->originalText, 'text' => $text]);
//		}

//		echo "</span><br>";

	}

	private function checkPos(string $char, $text = '', $i = 0)
	{
		if ($this->originalTextArr[$this->pos] === $char) {
			return;
		}

		if ($this->originalTextArr[$this->pos] === '\\'
			|| $this->originalTextArr[$this->pos + 1] === $text[$i]
		) {
			$this->pos++;
			return;
		}

		if ($this->originalTextArr[$this->pos] === $text[$i + 1]) {
			$this->pos--;
			return;
		}

		if (is_array($text)) {
			echo implode('', $text) . " -> $char != {$this->originalTextArr[$this->pos]}<br>";
		} else {
			echo "$text -> $char != {$this->originalTextArr[$this->pos]}<br>";
		}
	}

	private function makeContent(string $text, int $depth): array
	{
		$separatedText = $this->separateByOuterComma($text);

		$res = [];

		foreach ($separatedText as $txt) {
			// Если перед словом пробелы, нужно их учитывать
			$this->pos += mb_strlen($txt) - mb_strlen(ltrim($txt));

			$tmp = $this->recPar($txt, $depth);

			$res[] = $tmp;
		}

		return $res;
	}

	private function separateByOuterComma(string $text): array
	{
		$i = 0;
		$txt = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
		$char = $txt[$i];
		$stack = [];

		$str = '';
		$tmp = [];

		while ($char)
		{
			if ($char === '(') {
				array_push($stack, $i);
			} elseif ($char === ')') {
				array_pop($stack);
			}

			$str .= $char;
			if (empty($stack) && $str !== '') {
				$tmp[] = $str;
				$str = '';
			}

			$i++;
			$char = $txt[$i];
		}

		$el = '';
		$res = [];
		$inQuotes = false;
		$isSlashed = false;
		foreach ($tmp as $chars) {
			if ($isSlashed) {
				$isSlashed = false;
				$el .= $chars;
				continue;
			}

			if ($chars === '\\') {
				$isSlashed = true;
				continue;
			}

			if ($chars === '"') {
				$inQuotes = !$inQuotes;
			}
			if ($chars !== ',' || $inQuotes) {
				$el .= $chars;
			} elseif (!$inQuotes) {
				$res[] = $el;
				$el = '';
			}
		}

		if ($el) {
			$res[] = $el;
		}

		return $res;
	}

}

class Printer
{
	public static function prettyPrint($data): void
	{
		echo '<pre style="background-color: #333; color: #eee; padding: .5em; border-radius: 10px">'
			. json_encode($data, JSON_PRETTY_PRINT)
			. '</pre>';
	}

	public static function treeAsString(array $tree, string $treeStr = ''): string
	{
		foreach ($tree as $node) {
			foreach ($node as $depth => $value) {
				if (!empty($value['content'])) {
					$treeStr .= '<span style="margin-left: ' . (10 * ($depth - 1)) . 'px">'  . $value['args'] .'(</span><br>' ;
					$treeStr .= static::treeAsString($value['content']);
				} else {
					$treeStr .= '<span style="margin-left: ' . (10 * ($depth - 1)) . 'px">' . $value['args'] .'</span><br>' ;
				}
			}
		}
		return $treeStr . '<span style="margin-left: '
			. 10 * (array_keys($tree[0])[0] - 2)
			. 'px">)</span><br>' ;
	}

	public static function trimByOne($text, string $character): string
	{
		$textArr = is_array($text)
			? $text
			: preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);


		if ($textArr[0] === $character) {
			$textArr = array_slice($textArr, 1);
		}
		if (end($textArr) === $character) {
			$textArr = array_slice($textArr, 0, -1);
		}

		return implode('', $textArr);
	}
}

class Replacer
{
	private array $replaceable;
	private int $i;

	public function __construct() {}

	public function replaceArguments($string, $replaceable): string
	{
		$this->replaceable = $replaceable;

		return static::replaceInfiniteArg(
			static::replaceInputArg(
				str_replace(',', '', $string),
			)
		);
	}

	private function replaceInputArg($string): string
	{
		$this->i = 1;

		$string = str_replace(',', '', $string);

		while (mb_strpos($string, "{input{$this->i}}") !== false) {
			$string = str_replace(
				"{input{$this->i}}",
				Printer::trimByOne(
					preg_split('//u', $this->replaceable[$this->i - 1], -1, PREG_SPLIT_NO_EMPTY),
					'"'
				),
				$string
			);
			$this->i++;
		}

		return $string;
	}

	private function replaceInfiniteArg($string): string
	{
		$string = str_replace(',', '', $string);

		while (
			mb_strpos($string, '{infinite}') !== false
			&& $this->replaceable[$this->i - 1]
		) {
			$string = str_replace(
				"{infinite}",
				Printer::trimByOne(
					preg_split('//u', $this->replaceable[$this->i - 1], -1, PREG_SPLIT_NO_EMPTY), '"'
				) . '{infinite}',
				$string
			);
			$this->i++;
		}

		return str_replace("{infinite}", '', $string);
	}
}

function rutime($ru, $rus, $index) {
	return ($ru["ru_$index.tv_sec"]*1000 + intval($ru["ru_$index.tv_usec"]/1000))
		-  ($rus["ru_$index.tv_sec"]*1000 + intval($rus["ru_$index.tv_usec"]/1000));
}

$rustart = getrusage();

try {

	$par = new Parser(
		mb_ereg_replace('\s+', ' ', file_get_contents('/home/alex/PhpstormProjects/zkhdockertest/parrec/test.lte')),
		$replace
	);

	$par->generateText();
	$ru = getrusage();
} catch (\Throwable $e) {
	echo $e->getMessage();
}
echo "This process used " . rutime($ru, $rustart, "utime") .
	" ms for its computations\n";
