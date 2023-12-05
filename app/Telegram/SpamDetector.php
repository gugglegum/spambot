<?php

namespace App\Telegram;

use App\Helpers\TelegramHelper;
use Illuminate\Support\Collection;

class SpamDetector
{
    private \Aura\Sql\ExtendedPdo $pdo;
    private Collection $message;

    /** @var float Big positive values for good messages from trusted senders, small or negative values for bad messages from untrusted senders */
    public float $rate = 0.0;
    public int $messagesCountFromUser;
    public ?int $dateOfFirstUserMessage;
    public ?float $daysSinceFirstMessage;

    public SpamDetectorBadWordsStat $badWordStat;

    public function __construct(\Aura\Sql\ExtendedPdo $pdo, Collection $message, SpamDetectorBadWordsStat $badWordStat = null)
    {
        $this->pdo = $pdo;
        $this->message = $message;
        if ($badWordStat) {
            $this->badWordStat = $badWordStat;
        }
    }

    public function rate(): void
    {
        $chatId = TelegramHelper::convertChatId($this->message->chat->id, $this->message->chat->type);
        $messageFromId = TelegramHelper::getMessageFromId($this->message);

        $this->messagesCountFromUser = $this->getMessagesCountFromUser($messageFromId, $chatId);
        $this->dateOfFirstUserMessage = $this->getDateOfFirstUserMessage($messageFromId, $chatId);
        $this->daysSinceFirstMessage = $this->dateOfFirstUserMessage ? (time() - $this->dateOfFirstUserMessage) / 3600 / 24 : null;

//        $this->rateMessagesCount();
//        $this->rateDateOfFirstUserMessage();
        $this->rateBadWords();
        $this->rateMixedLetters();
        $this->rateMaskedDigits();
    }

    private function rateMessagesCount(): void
    {
        if ($this->messagesCountFromUser > 0) {
            $rate = log($this->messagesCountFromUser + 9, 10) * 10;
        } else {
            $rate = 0;
        }
        echo "rateMessagesCount: " . round($rate, 2) . "\n";
        $this->rate += $rate;
    }

    private function rateDateOfFirstUserMessage(): void
    {
        if ($this->dateOfFirstUserMessage) {
            if ($this->daysSinceFirstMessage > 0) {
                $rate = log($this->daysSinceFirstMessage + 9, 10) * 10;
            } else {
                $rate = 0;
            }
        } else {
            $rate = 0;
        }
        echo "rateDateOfFirstUserMessage: " . round($rate, 2) . "\n";
        $this->rate += $rate;
    }

    private function rateBadWords(): void
    {
        $words = self::extractWords((string) $this->message->text);
        //var_dump($words);
        $badWordSequences = [
            ['предлагаем партнерство', -10],
            ['(ищ(у|ем)|нужны|набор|набираем) партнер(ы|ов)', -10],
            ['(набор|нужны|набираю|набираем)( (люди|людей))?( новую)?( в)? команду', -10],
            ['(людей|человека)(( к)? себе)? в команду', -10],
            ['люд(и|ей) для заработка', -10],
            ['набираю команду партнеров', -10],
            ['ищу людей в сферу крипты', -10],
            ['ищем сотрудников для удаленного заработка', -10],
            ['требуются люди', -10],
            ['нужны люди', -10],
            ['набираем людей на сотрудничество', -10],
            ['возможность сотрудничества', -10],
            ['в( новом)? направлении P2P', -10],
            ['обучение с нуля', -10],
            ['(бесплатно(е)?|есть|всему) обуч(им|аем|ение)', -10],
            ['обуч(им|аем|ение) бесплатно(е)?', -10],
            ['удаленн(ая|ую) (подработк(а|у)|работ(а|у))', -10],
            ['(работа|занятость) удаленная', -10],
            ['удаленного заработка', -10],
            ['есть подработка', -10],
            ['подработка дистанционно', -10],
            ['(от|до) \d{2,6}\s?(\$|USDT?|долларов|руб(лей)?) в (нед|неделю|д|день|сут|сутки|мес|месяц)', -10],
            ['(от|до) \$\d{2,6} в (нед|неделю|д|день|сут|сутки|мес|месяц)', -10],
            ['в (нед|неделю|д|день|сут|сутки|мес|месяц) (от|до) \d{2,6}\s?(\$|USDT?|долларов|руб(лей)?)', -10],
            ['в (нед|неделю|д|день|сут|сутки|мес|месяц) (от|до) \$\d{2,6}', -10],
            ['\d{2,6}\s?(\$|USDT?|долларов|руб(лей)?)? \d{2,6}\s?(\$|USDT?|долларов|руб(лей)?) в (нед|неделю|д|день|сут|сутки|мес|месяц)', -10],
            ['\$?\d{2,6} \$\d{2,6} в (нед|неделю|д|день|сут|сутки|мес|месяц)', -10],
            ['интересна крипта', -10],
            ['места ограничены', -10],
            ['в (сфере|направлении) (удаленного заработка|крипты|криптовалют(ы)?|crypto|цифровых (валют|активов))', -10],
            ['P2P (trading|трейдинг(у|а|е)?)', -10],
            ['партнеров в( нашу| новую)? команду', -10],
            ['финансово независимыми', -10],
            ['с цифровыми валютами', -10],
            ['возможность заработка', -10],
            ['в команду для заработка', -10],
            ['в сфере цифровой валюты', -10],
            ['дополнительный доход', -10],
            ['(возраст|только) \d{2}\+', -10],
            ['(от|с) \d{2} лет', -10],
            ['(легкое?|можно|не\s?сложно|возможность) совмещ(ать|ается|ение)', -10],
            ['онлайн заработ(ок|ка)', -10],
            ['возможность работать с телефона', -10],
            ['доход(ом)? от', -10],
            ['с ежедневным доходом', -10],
            ['заработок от', -10],
            ['часа в день', -10],
            ['для расширения команды', -10],
            ['берём без опыта', -10],
            ['с опытом и без', -10],
            ['всему научим', -10],
            ['опыт необязателен', -10],
            ['заработок на (бинанс|binance)', -10],
            ['заработок в месяц', -10],
            ['от суммы вложения', -10],
            ['сфера (криптовалюты|крипто|crypto)', -10],
            ['за детальной информацией', -10],
            ['пассивный доход зависит от вашего желания работать', -10],
            ['сопровождение и (помощь|доведение)', -10],
            ['всему обучаем и сопровождаем', -10],
            ['в сфере крипты', -10],
            ['crypto', -10],
            ['нет опыта не страшно', -10],
            ['мы всему научим', -10],
            ['(за полной информацией|заинтересованным|для деталей|пиши мне|пишите|не стесняемся|кому интересно) \+ в (лс|личку|личные)', -10],
            ['Пиши \+', -10],
            ['(занятость|займет|занимает|нужно всего) [^\s]+( [^\s]+)?( [^\s]+)? часа', -10],
            ['свободных [^\s]+( [^\s]+)?( [^\s]+)? часа в день', -10],
            ['(до|от) [^\s]+( [^\s]+)?( [^\s]+)? час(у|а|ов) в день', -10],
            ['работа (на дому|в интернете)', -10],
            ['работать можно с телефона', -10],
            ['интересное предложения для любого пола', -10],
            ['люди для работы (с|из) дома', -10],
            ['всему обучаем с вас денег не берем', -10],
            ['от вас нужно выход в интернет', -10],
            ['бонус к доходу', -10],
            ['для записи на обучение', -10],
            ['доводим до результата', -10],
            ['команда сильных коллег и экспертов', -10],
            ['обмен ценным опытом', -10],
            ['берем людей с опытом и без', -10],
            ['доводим до результата', -10],
            ['повысьте свое благосостояние', -10],
            ['не выходя из дома', -10],
            ['новой прогрессирующей сфере дохода', -10],
            ['обучение онлайн бесплатно', -10],
            ['с опытом в крипте', -10],
            ['для дистанционной работы', -10],
            ['возможность работы с телефона', -10],
            ['сами управляете своим депозитом', -10],
            ['от вашей чистой прибыли', -10],
            ['на официальных платформах', -10],
            ['(предложить|предлаг(аю|аем)) вам высокооплачиваемую', -10],
            ['с гибким рабочим графиком', -10],
            ['по поводу работы', -10],
            ['можно работать с любой точки мира', -10],
            ['работа со своих устройств', -10],
            ['c любых устройств', -10],
            ['хорошо заработать', -10],
            ['зависит от скорости работы', -10],
            ['все легально без предоплат', -10],
            ['удаленная занятость в новом направлении', -10],
            ['без предоплат', -10],
            ['без вложений', -10],
            ['с вложениями', -10],
            ['новое прибыльное направление', -10],
            ['есть тема белая', -10],
            ['предлагаю партнерство', -10],
            ['сфере Crypto', -10],
            ['только заинтересованные', -10],
            ['заработок c первого дня', -10],
            ['выведем на результат', -10],
            ['быстрый заработок', -10],
            ['для сотрудничества', -10],
            ['участников( для)?( нашей)? команды', -10],
            ['опыт не требуется', -10],
            ['всему науч(у|им) на обучении', -10],
            ['чист(ая|ой) прибыл(ь|и)', -10],
            ['каждый сможет справиться с работой', -10],
            ['быть всегда на связи', -10],
            ['готов зарабатывать уже сейчас', -10],
            ['пару часов свободного времени', -10],
            ['желание зарабатывать', -10],
            ['устройство с доступом в интернет', -10],
            ['интересное направление работы', -10],
            ['постоянная онлайн поддержка', -10],
//            ['', -10],
//            ['', -10],
//            ['', -10],
//            ['', -10],
        ];
        if (isset($this->badWordStat) && !isset($this->badWordStat->stat)) {
            foreach ($badWordSequences as $badWordSequenceData) {
                $this->badWordStat->stat[$badWordSequenceData[0]] = 0;
            }
        }

        $totalRate = 0;
        foreach ($badWordSequences as $badWordSequenceData) {
            list($badWordPhrase, $rate) = $badWordSequenceData;
            //var_dump(self::splitWords($badWordPhrase));
            if (self::isWordSubsequence($words, self::splitWords($badWordPhrase))) {
                echo "\tMatched \"{$badWordPhrase}\"\n";
                $totalRate += $rate;
                if (isset($this->badWordStat)) {
                    $this->badWordStat->stat[$badWordPhrase]++;
                }
            }
        }
        echo "rateBadWords: " . $totalRate . "\n";
        $this->rate += $totalRate;
    }

    private function rateMixedLetters(): void
    {
        $rate = 0;
        $words = self::canonizeWords(self::extractWords((string) $this->message->text));
        foreach ($words as $word) {
            $hasRussian = false;
            $hasEnglish = false;
            if (preg_match('/[а-я]/ui', $word)) { // has Russia
                $hasRussian = true;
            }
            if (preg_match('/[a-z]/ui', $word)) { // has English
                $hasEnglish = true;
            }
            if ($hasRussian && $hasEnglish) {
                if (preg_match('/^[a-z]{2,}[а-я]+$/ui', $word)) { // exclude words like "IDшник"
                    continue;
                }
                $rate -= 1;
                echo "\tMixed letters in \"{$word}\"\n";
            }
        }
        echo "rateMixedLetters: " . $rate . "\n";
        $this->rate += $rate;
    }

    private function rateMaskedDigits(): void
    {
        $rate = 0;
        $words = self::canonizeWords(self::extractWords((string) $this->message->text));
        foreach ($words as $word) {
            if (preg_match('/^(\d+[oо]+)+\d*$/ui', $word)) {
                $rate -= 25;
                echo "\tMasked digits in \"{$word}\"\n";
            }
        }
        echo "rateMaskedDigits: " . $rate . "\n";
        $this->rate += $rate;
    }

    private static function isWordSubsequence(array $words, array $wordsSequence): bool
    {
        $words = self::canonizeWords($words);
        $wordsSequence = self::canonizeWords($wordsSequence);
        if (count($wordsSequence) == 0) {
            return false;
        }


        return (bool) preg_match('/(?:^|\s)' . self::enrichRegExp(implode(' ', $wordsSequence)) . '(?:$|\s)/u', implode(' ', $words));
//        $j = 0;
//        for ($i = 0; $i < count($words); $i++) {
//            if (preg_match("/^{$wordsSequence[$j]}$/u", $words[$i])) {
//                $j++;
//            } else {
//                $j = 0;
//            }
//            if ($j == count($wordsSequence)) {
//                return true;
//            }
//        }
//
//        return false;
    }

    private static function enrichRegExp(string $regexp): string
    {
        $enriches = [
            'а' => '(а|a|4)',
            'б' => '(б|6)',
            'в' => '(в|b)',
            'г' => '(г|r)',
            'д' => '(д|d)',
            'е' => '(е|e)',
            'з' => '(з|3)',
            'и' => '(и|u)',
            'к' => '(к|k)',
            'м' => '(м|m)',
            'о' => '(о|o|0)',
            'р' => '(р|p)',
            'с' => '(с|c)',
            'т' => '(т|t)',
            'у' => '(у|y)',
            'х' => '(х|x)',
            'a' => '(a|а)',
            'b' => '(b|в)',
            'c' => '(c|с)',
            'e' => '(e|е)',
            'g' => '(g|г)',
            'i' => '(i|1)',
            'k' => '(k|к)',
            'l' => '(l|1)',
            'o' => '(o|о|0)',
            'p' => '(p|р)',
            'x' => '(x|х)',
            'y' => '(y|у)',
        ];

        return str_replace(array_keys($enriches), array_values($enriches), $regexp);
    }

    private static function canonizeWords(array $words): array
    {
        // 💲 18➕
        for ($i = 0; $i < count($words); $i++) {
            $words[$i] = mb_strtolower($words[$i]);
            $words[$i] = str_replace('ё', 'е', $words[$i]);
        }
        return $words;
    }

    private function getMessagesCountFromUser(string $fromId, int $chatId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) `count` FROM `messages` WHERE `group_id` = :group_id AND `from_id` = :from_id");
        $stmt->execute([
            'group_id' => $chatId,
            'from_id' => $fromId,
        ]);
        $count = $stmt->fetchColumn();
        $stmt->closeCursor();
        return (int) $count;
    }

    private function getDateOfFirstUserMessage(string $fromId, int $chatId): ?int
    {
        $stmt = $this->pdo->prepare("SELECT `date_unixtime` FROM `messages` WHERE `group_id` = :group_id AND `from_id` = :from_id ORDER BY `date_unixtime`");
        $stmt->execute([
            'group_id' => $chatId,
            'from_id' => $fromId,
        ]);
        $date_unixtime = $stmt->fetchColumn();
//        var_dump($date_unixtime);die;
        $stmt->closeCursor();
        return $date_unixtime ? (int) $date_unixtime : null;
    }

    private static function splitWords(string $phrase): array
    {
        $words = preg_split('/\s+/ui', $phrase, -1, PREG_SPLIT_NO_EMPTY);
//        echo "\nWords: "; var_dump($words);die;
        return $words;
    }

    private static function extractWords(string $text): array
    {
        preg_match_all('/[а-яёa-z$0-9+]+/ui', $text, $m);
        return $m[0];
//        var_dump($m[0]);die;
//        $words = preg_split('/[\s.,()!@#$%^&*\[\]"\'\\\\\/]+/ui', $text, -1, PREG_SPLIT_NO_EMPTY);
//        echo "\nWords: "; var_dump($words);die;
    }
}
