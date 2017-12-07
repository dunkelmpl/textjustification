<?php
class TextJustifier {
    /**
     * TextJustifier constructor.
     *
     * @param string[] $words
     */
    public function __construct($words)
    {
        $this->words = array_map('trim', $words);
    }

    /**
     * @param int $lineWidth
     *
     * @return string[] lines with justified text
     */
    public function justifyText($lineWidth)
    {
        $numWords = count($this->words);
        if ($numWords < 1) {
            return [];
        }

        if ($numWords > 1) {
            $costs = $this->getWordsToLinesCost($lineWidth);
            $wordWrapPositions = $this->getWordWrapPositionsByCosts($costs);
        } else { //1 word
            $wordWrapPositions = [1];
        }

        return $this->getJustifiedTextByWordWrapPositions($lineWidth, $wordWrapPositions);
    }

    /**
     * Gets cost of every word being on every line.
     *
     * @param int $lineWidth
     *
     * @return int[][]
     * @throws InvalidArgumentException
     */
    protected function getWordsToLinesCost($lineWidth)
    {
        $numWords = count($this->words);
        
        /* @var int[][] $costs */
        $costs = [];

        for ($wordIndex = 0; $wordIndex < $numWords; $wordIndex++) {
            $costs[$wordIndex] = array_fill(0, $numWords, INF);

            /** @var string $word */
            $word = $this->words[$wordIndex];
            if (strlen($word) > $lineWidth) {
                $this->throwInvalidWordLength($lineWidth);
            }

            $sentenceLen = 0;
            for ($line = $wordIndex; $line < $numWords; $line++) {
                $sentenceLen += ($sentenceLen > 0 ? 1 : 0) + strlen($this->words[$line]);

                if ($sentenceLen > $lineWidth) {
                    break;
                }

                $costs[$wordIndex][$line] = ($lineWidth - $sentenceLen)**2;
            }
        }

        return $costs;
    }

    /**
     * @param int[][] $costs
     *
     * @return int[]
     */
    protected function getWordWrapPositionsByCosts($costs)
    {
        $numWords = count($this->words);

        /** @var int[] $minWordCosts */
        $minWordCosts = array_fill(0, $numWords, INF);

        /** @var int[] $wordWrapPositions */
        $wordWrapPositions = array_fill(0, $numWords, $numWords+1);

        for ($wordIndex = $numWords - 1; $wordIndex >= 0; $wordIndex--) {
            //if can fit sentence from current word up to the end without splitting then that's preferable option.
            if ($costs[$wordIndex][$numWords - 1] == INF) {
                for ($splitPos = $numWords - 1; $splitPos >= $wordIndex; $splitPos--) {
                    if (($costs[$wordIndex][$splitPos] != INF) && ($minWordCosts[$splitPos+1] != INF)) {
                        $newCost = $costs[$wordIndex][$splitPos] + $minWordCosts[$splitPos+1];

                        if ($newCost < $minWordCosts[$wordIndex]) {
                            $minWordCosts[$wordIndex] = $newCost;
                            $wordWrapPositions[$wordIndex] = $splitPos + 1;
                        }
                    }
                }
            } else {
                $minWordCosts[$wordIndex] = $costs[$wordIndex][$numWords - 1];
                $wordWrapPositions[$wordIndex] = $numWords;
            }
        }

        return $wordWrapPositions;
    }

    /**
     * @param int   $lineWidth
     * @param int[] $wordWrapPositions
     *
     * @return string[][]
     */
    protected function getJustifiedTextByWordWrapPositions($lineWidth, $wordWrapPositions)
    {
        $justifiedText = [];

        $wordIndex = 0;
        $rowIndex = 0;
        while ($wordIndex < count($this->words)) {
            $nextWordWrapPos = $wordWrapPositions[$wordIndex];

            $row = [];
            $rowLength = 0;
            while ($wordIndex < $nextWordWrapPos) {
                $row[] = $this->words[$wordIndex];
                $rowLength += ($rowLength > 0 ? 1 : 0) + strlen($this->words[$wordIndex]);
                $wordIndex++;
            }

            if ($rowLength < $lineWidth) {
                $cellIndex = 0;
                $paddingCellsQuantity = count($row) - 1;
                if ($paddingCellsQuantity == 0) { //1 word row
                    $paddingCellsQuantity = 1;
                }
                
                do {
                    $row[$cellIndex] .= ' ';
                    $rowLength++;
                    $cellIndex = ($cellIndex+1)%$paddingCellsQuantity;
                } while ($rowLength < $lineWidth);
            }
            $justifiedText[$rowIndex] = implode(' ', $row);
            $rowIndex++;
        }

        return $justifiedText;
    }

    /**
     * @param int $lineWidth
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function throwInvalidWordLength($lineWidth)
    {
        throw new InvalidArgumentException(
            "Word is too long to fit line of width " . $lineWidth
        );
    }

    /** @var string[] */
    private $words;
}


/**
 *
 * @param mixed          $expected
 * @param callable       $actual
 * @param bool           $strict [optional]
 */
function testEquals($expected, $actual, $strict = true)
{
    $actualValue = call_user_func($actual);
    
    $result = $strict
        ? $expected === $actualValue
        : $expected == $actualValue
    ;
    
    if (!$result) {
        echo 'Failed asserting that expected ' . var_export($expected, true) . ' equals to ' . var_export($actualValue, true);
    }
    
    return $result;
}

/**
 *
 * @param mixed    $expected
 * @param callable actual
 */
function testExpectException($exceptionClassName, callable $actual)
{
    try {
        call_user_func($actual);
    } catch (Exception $ex) {
        if ($ex instanceof $exceptionClassName) {
            return true;
        } else {
            throw $ex;
        }
    }
    
    return false;
}

function runTest(array $testData, &$buf)
{
    $function = $testData['assert'];
    $params   = $testData['params'];

    $unexpectedException = null;
    $outText = "";
    try {
        ob_start();
        $result = call_user_func_array($function, $params);
        $outText = ob_get_contents();
        ob_end_clean();
    } catch (Exception $ex) {
        $unexpectedException = $ex;
        $result = false;
    }
    
    if ($result) {
        echo '.';
    } else {
        echo 'F';
        
        if ($unexpectedException) {
            $buf .= "Unexpected exception: " . $unexpectedException . "\n";
        }
        
        if ($outText != "") {
            $buf .= $outText . "\n";
        }
    }
}

function main()
{
    $result = 0;
    
    $errorBuf = "";
    
    $tests = [
        [
            'assert' => 'testEquals',
            'params' => [
                [
                    "This    is    an",
                    "example  of text",
                    "justification   ",
                ],
                function() {
                    return (new TextJustifier(
                        ["This", "is", "an", "example", "of", "text", "justification"]
                    ))->justifyText(16);
                }
            ],
        ],
        [
            'assert' => 'testEquals',
            'params' => [
                [
                    "This   is   also",
                    "good     example",
                    "of          text",
                    "justification   ",
                ],
                function() {
                    return (new TextJustifier(
                        ["This", "is", "also", "good", "example", "of", "text", "justification"]
                    ))->justifyText(16);
                }
            ],
        ],
        [
            'assert' => 'testEquals',
            'params' => [
                [
                    "This  is  one of",
                    "the corner cases",
                    "somejustsolong  ",
                    "word  could make",
                ],
                function() {
                    return (new TextJustifier([
                        "This", "is", "one", "of", "the", "corner", "cases",
                        "somejustsolong", "word", "could", "make"
                    ]))->justifyText(16);
                }
            ],
        ],
        [
            'assert' => 'testExpectException',
            'params' => [
                InvalidArgumentException::class,
                function() {
                    return (new TextJustifier([
                        "This", "test", "case", "has", "a",
                        "seventeen(17-chr)", "long", "word",
                        "and", "thus", "we", "expect", "exception"
                    ]))->justifyText(16);
                }
            ],
        ],
        [
            'assert' => 'testEquals',
            'params' => [
                [
                    "This   test  case",
                    "even    with    a",
                    "seventeen(17-chr)",
                    "long  word should",
                    "not    throw   an",
                    "exception   since",
                    "maxLen here is 17",
                ],
                function() {
                    return (new TextJustifier([
                        "This", "test", "case", "even", "with", "a",
                        "seventeen(17-chr)", "long", "word",
                        "should", "not", "throw", "an", "exception",
                        "since", "maxLen", "here", "is", "17",
                    ]))->justifyText(17);
                },
            ],
        ],
        [
            'assert' => 'testEquals',
            'params' => [
                [],
                function() {
                    return (new TextJustifier([]))->justifyText(16);
                },
            ],
        ],
        [
            'assert' => 'testEquals',
            'params' => [
                ["Onewordtest     "],
                function() {
                    return (new TextJustifier(["Onewordtest"]))->justifyText(16);
                },
            ],
        ],
        [
            'assert' => 'testEquals',
            'params' => [
                [
                    "Non-trimmed         ",
                    "words           test"
                ],
                function() {
                    return (new TextJustifier(["Non-trimmed    ", "   words", "         test          "]))->justifyText(20);
                },
            ],
        ],
    ];
    
    foreach ($tests as $testData) {
        runTest($testData, $errorBuf);
    }
    
    if ($errorBuf != "") {
        echo "\n\n" . $errorBuf. "\n\nFAILED!\n";
        
        $result = 1;
    } else {
        echo "\n\nOK (". count($tests)." tests passed)\n";
    }
    
    return $result;
}

exit(main());

?>