<?php
declare(strict_types=1);

namespace PremiereRelayArchive;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * 날짜를 나타내는 불변(Immutable) 값 객체.
 * YYYY-MM-DD 형식의 문자열 변환 및 날짜 관련 로직을 캡슐화합니다.
 */
final class ArchiveDate
{
    public const FORMAT = 'Y-m-d';
    private DateTimeImmutable $date;

    private function __construct(DateTimeImmutable $date)
    {
        // 시간, 분, 초는 0으로 고정하여 날짜만 비교하도록 보장
        $this->date = $date->setTime(0, 0, 0);
    }

    /**
     * 문자열로부터 ArchiveDate 객체를 생성합니다.
     *
     * @param string $dateString YYYY-MM-DD 형식의 날짜 문자열
     * @return ArchiveDate
     * @throws InvalidArgumentException
     */
    public static function fromString(string $dateString): self
    {
        $date = DateTimeImmutable::createFromFormat(self::FORMAT, $dateString);
        if ($date === false || $date->format(self::FORMAT) !== $dateString) {
            throw new InvalidArgumentException("잘못된 날짜 형식: {$dateString}, YYYY-MM-DD 형식이 필요합니다.");
        }
        return new self($date);
    }

    public static function today(): self
    {
        return new self(new DateTimeImmutable('now', new \DateTimeZone('Asia/Seoul')));
    }

    public function format(): string
    {
        return $this->date->format(self::FORMAT);
    }

    public function getYear(): string
    {
        return $this->date->format('Y');
    }

    public function getMonth(): string
    {
        return $this->date->format('m');
    }

    public function getYesterday(): self
    {
        return new self($this->date->sub(new \DateInterval('P1D')));
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
