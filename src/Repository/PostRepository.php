<?php

declare(strict_types=1);

namespace GnuCms\Repository;

use GnuCms\Db\Connection;
use GnuCms\Support\Clock;
use GnuCms\Support\Json;

final class PostRepository
{
    /**
     * 기본 조회 컬럼. guest_password 가 빠져 있는 것이 핵심이다.
     * 이 목록에 없는 컬럼은 findWithSecret() 로만 얻을 수 있다.
     */
    private const COLUMNS = 'id, board_id, category, title, content, author_id, author_name, author_ip,'
        . ' is_notice, notice_scope, is_secret, view_count, comment_count, attachments, image_key,'
        . ' created_at, updated_at, deleted_at';

    private const DEFAULTS = [
        'category'       => null,
        'author_id'      => null,
        'guest_password' => null,
        'author_ip'      => null,
        'is_notice'      => 0,
        'notice_scope'   => 'board',
        'is_secret'      => 0,
        'view_count'     => 0,
        'comment_count'  => 0,
        'attachments'    => [],
        'image_key'      => null,
    ];

    /** LIKE 검색의 이스케이프 문자. 백슬래시는 MySQL 문자열 리터럴에서 한 번 더 처리되므로 피한다. */
    private const LIKE_ESCAPE = '!';

    /** @var Connection */
    private $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function find(int $id): ?array
    {
        $row = $this->db->selectOne(
            'SELECT ' . self::COLUMNS . ' FROM ' . $this->db->table('posts') . ' WHERE id = ?',
            [$id]
        );

        return $row === null ? null : $this->hydrateMany([$row])[0];
    }

    public function findWithSecret(int $id): ?array
    {
        $row = $this->db->selectOne(
            'SELECT ' . self::COLUMNS . ', guest_password FROM ' . $this->db->table('posts') . ' WHERE id = ?',
            [$id]
        );

        return $row === null ? null : $this->hydrateMany([$row])[0];
    }

    /** @return array{rows: array, total: int} */
    public function paginate(
        int $boardId,
        int $page,
        int $perPage,
        ?string $q = null,
        ?string $category = null,
        bool $includeDeleted = false
    ): array {
        // 삭제 글 포함은 관리자 화면의 복구 목록을 위한 것이다.
        // 서비스 계층에서 관리 권한을 확인한 뒤에만 true 로 넘어온다.
        $terms = $this->searchTerms($q);
        $where = $includeDeleted
            ? 'board_id = :board_id'
            : 'board_id = :board_id AND deleted_at IS NULL';
        // 평소 목록에서는 공지를 고정 영역에 따로 내지만 검색 결과에는 함께 넣는다.
        if ($terms === []) {
            $where .= ' AND is_notice = 0';
        }
        $params = ['board_id' => $boardId];

        foreach ($terms as $index => $term) {
            $titleKey = 'q' . $index . '_title';
            $bodyKey = 'q' . $index . '_body';
            $where .= ' AND (LOWER(title) LIKE LOWER(:' . $titleKey . ') ESCAPE \'' . self::LIKE_ESCAPE . '\''
                . ' OR (is_secret = 0 AND LOWER(content) LIKE LOWER(:' . $bodyKey . ') ESCAPE \'' . self::LIKE_ESCAPE . '\'))';
            $pattern = '%' . $this->escapeLike($term) . '%';
            $params[$titleKey] = $pattern;
            $params[$bodyKey] = $pattern;
        }

        if ($category !== null && $category !== '') {
            $where .= ' AND category = :category';
            $params['category'] = $category;
        }

        $total = (int) $this->db->selectOne(
            'SELECT COUNT(*) AS c FROM ' . $this->db->table('posts') . ' WHERE ' . $where,
            $params
        )['c'];

        $offset = max(0, ($page - 1) * $perPage);
        $rows = $this->db->select(
            'SELECT ' . self::COLUMNS . ' FROM ' . $this->db->table('posts')
            . ' WHERE ' . $where . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );

        return ['rows' => $this->hydrateMany($rows), 'total' => $total];
    }

    /**
     * 게시판을 가로질러 글을 훑는다. 관리 화면 전용이며, 서비스 계층에서 관리 권한을
     * 확인한 뒤에만 호출한다. 공지도 함께 보여 준다.
     */
    public function paginateAll(
        int $page,
        int $perPage,
        ?string $q = null,
        ?int $boardId = null,
        bool $includeDeleted = false,
        ?array $boardIds = null,
        ?int $authorId = null
    ): array {
        $where = $includeDeleted ? '1 = 1' : 'deleted_at IS NULL';
        $params = [];

        // 읽을 수 있는 게시판만. 빈 목록이면 아무 글도 없다.
        if ($boardIds !== null) {
            if ($boardIds === []) {
                return ['rows' => [], 'total' => 0];
            }
            $marks = [];
            foreach (array_values($boardIds) as $i => $id) {
                $marks[] = ':b' . $i;
                $params['b' . $i] = (int) $id;
            }
            $where .= ' AND board_id IN (' . implode(', ', $marks) . ')';
        }

        foreach ($this->searchTerms($q) as $index => $term) {
            $titleKey = 'q' . $index . '_title';
            $bodyKey = 'q' . $index . '_body';
            $where .= ' AND (LOWER(title) LIKE LOWER(:' . $titleKey . ') ESCAPE \'' . self::LIKE_ESCAPE . '\''
                . ' OR (is_secret = 0 AND LOWER(content) LIKE LOWER(:' . $bodyKey . ') ESCAPE \'' . self::LIKE_ESCAPE . '\'))';
            $pattern = '%' . $this->escapeLike($term) . '%';
            $params[$titleKey] = $pattern;
            $params[$bodyKey] = $pattern;
        }

        if ($boardId !== null) {
            $where .= ' AND board_id = :board_id';
            $params['board_id'] = $boardId;
        }

        if ($authorId !== null) {
            $where .= ' AND author_id = :author_id';
            $params['author_id'] = (string) $authorId;
        }

        $total = (int) $this->db->selectOne(
            'SELECT COUNT(*) AS c FROM ' . $this->db->table('posts') . ' WHERE ' . $where,
            $params
        )['c'];

        $offset = max(0, ($page - 1) * $perPage);
        $rows = $this->db->select(
            'SELECT ' . self::COLUMNS . ' FROM ' . $this->db->table('posts')
            . ' WHERE ' . $where . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );

        return ['rows' => $this->hydrateMany($rows), 'total' => $total];
    }

    /**
     * 목록 맨 위에 붙일 공지. 이 게시판의 공지와, 읽을 수 있는 게시판에 올라온
     * 전체 공지를 함께 뽑는다. 전체 공지가 먼저, 각각 최신순이다.
     *
     * @param int[] $readableBoardIds 읽을 수 있는 게시판 번호. 전체 공지는 이 안에서만 온다
     */
    public function notices(int $boardId, array $readableBoardIds = []): array
    {
        $params = ['board_id' => $boardId];
        $globalClause = '';
        if ($readableBoardIds !== []) {
            $marks = [];
            foreach (array_values($readableBoardIds) as $i => $id) {
                $marks[] = ':r' . $i;
                $params['r' . $i] = (int) $id;
            }
            $globalClause = " OR (notice_scope = 'global' AND board_id IN (" . implode(', ', $marks) . '))';
        }

        $rows = $this->db->select(
            'SELECT ' . self::COLUMNS . ' FROM ' . $this->db->table('posts')
            . ' WHERE deleted_at IS NULL AND is_notice = 1'
            . ' AND (board_id = :board_id' . $globalClause . ')'
            // 전체 공지를 먼저. 방언마다 불리언 정렬이 달라 CASE 로 적는다.
            . " ORDER BY CASE WHEN notice_scope = 'global' THEN 0 ELSE 1 END, id DESC",
            $params
        );

        return $this->hydrateMany($rows);
    }

    /** 메인 화면에 표시할 게시판별 최신 글을 가져온다. */
    public function latest(int $boardId, int $limit = 5): array
    {
        $limit = max(1, min(10, $limit));
        $rows = $this->db->select(
            'SELECT ' . self::COLUMNS . ' FROM ' . $this->db->table('posts')
            . ' WHERE board_id = ? AND deleted_at IS NULL ORDER BY id DESC LIMIT ' . $limit,
            [$boardId]
        );

        return $this->hydrateMany($rows);
    }

    public function create(array $data): int
    {
        $now = Clock::now();
        $row = array_merge(self::DEFAULTS, $data, [
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return (int) $this->db->insert('posts', $this->dehydrate($row));
    }

    public function update(int $id, array $data): void
    {
        unset($data['id'], $data['board_id'], $data['created_at']);
        $data['updated_at'] = Clock::now();

        $this->db->update('posts', $this->dehydrate($data), 'id = :id', ['id' => $id]);
    }

    public function softDelete(int $id): void
    {
        $this->db->update('posts', ['deleted_at' => Clock::now()], 'id = :id', ['id' => $id]);
    }

    public function restore(int $id): void
    {
        $this->db->update('posts', ['deleted_at' => null], 'id = :id', ['id' => $id]);
    }

    public function deleteByBoard(int $boardId): void
    {
        $this->db->delete('posts', 'board_id = :board_id', ['board_id' => $boardId]);
    }

    /**
     * 모든 글이 참조하는 첨부 경로. 고아 파일 정리에 쓴다.
     * MySQL 5.7 에 JSON 함수가 없으므로 SQL 로 걸러내지 않고 PHP 로 모은다.
     *
     * @return string[]
     */
    public function allAttachmentPaths(): array
    {
        $rows = $this->db->select(
            'SELECT attachments FROM ' . $this->db->table('posts') . ' WHERE attachments IS NOT NULL'
        );

        $paths = [];
        foreach ($rows as $row) {
            $raw = (string) $row['attachments'];
            if ($raw === '' || $raw === '[]') {
                continue;
            }
            foreach (Json::decode($raw) as $file) {
                if (isset($file['path'])) {
                    $paths[] = (string) $file['path'];
                }
            }
        }

        return $paths;
    }

    /**
     * 게시판 안에서 분류 이름을 한꺼번에 바꾼다. 삭제된 글도 함께 바꾼다.
     * 복구했을 때 혼자만 옛 이름으로 남아 있으면 안 되기 때문이다.
     *
     * updated_at 은 건드리지 않는다. 글 내용이 바뀐 것이 아니라
     * 게시판 설정이 바뀐 것이다.
     */
    public function renameCategory(int $boardId, string $from, string $to): int
    {
        return $this->db->update(
            'posts',
            ['category' => $to],
            'board_id = :board_id AND category = :from_category',
            ['board_id' => $boardId, 'from_category' => $from]
        );
    }

    public function incrementViews(int $id): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('posts') . ' SET view_count = view_count + 1 WHERE id = ?',
            [$id]
        );
    }

    /**
     * 최신순 목록 기준 이전(큰 id, 더 최신)·다음(작은 id, 더 오래됨) 글.
     * 삭제된 글은 건너뛴다.
     * $boardIds 가 주어지면 읽을 수 있는 전체 게시판 범위, 아니면 한 게시판 범위다.
     *
     * @return array{previous:?array,next:?array}
     */
    public function adjacent(int $id, int $boardId, ?array $boardIds = null): array
    {
        $params = ['current_id' => $id];
        if ($boardIds === null) {
            $scope = 'board_id = :board_id';
            $params['board_id'] = $boardId;
        } else {
            if ($boardIds === []) return ['previous' => null, 'next' => null];
            $marks = [];
            foreach (array_values($boardIds) as $index => $readableId) {
                $key = 'board_' . $index;
                $marks[] = ':' . $key;
                $params[$key] = (int) $readableId;
            }
            $scope = 'board_id IN (' . implode(', ', $marks) . ')';
        }
        $base = ' FROM ' . $this->db->table('posts')
            . ' WHERE deleted_at IS NULL AND ' . $scope;
        $columns = 'SELECT id, board_id, title, is_secret';

        return [
            'previous' => $this->db->selectOne($columns . $base
                . ' AND id > :current_id ORDER BY id ASC LIMIT 1', $params),
            'next' => $this->db->selectOne($columns . $base
                . ' AND id < :current_id ORDER BY id DESC LIMIT 1', $params),
        ];
    }

    /**
     * 사이트맵과 RSS에 내보낼 공개 글. 읽기 가능한 게시판 번호는 호출자가 ACL로 고른다.
     * 비밀글은 제목조차 검색 피드로 흘리지 않는다.
     *
     * @param int[] $boardIds
     */
    public function publicFeedRows(array $boardIds, int $limit = 100, ?int $boardId = null): array
    {
        $boardIds = array_values(array_unique(array_map('intval', $boardIds)));
        if ($boardIds === []) return [];

        $params = [];
        $marks = [];
        foreach ($boardIds as $index => $readableId) {
            $key = 'board_' . $index;
            $marks[] = ':' . $key;
            $params[$key] = $readableId;
        }
        $where = 'deleted_at IS NULL AND is_secret = 0 AND board_id IN (' . implode(', ', $marks) . ')';
        if ($boardId !== null) {
            $where .= ' AND board_id = :selected_board';
            $params['selected_board'] = $boardId;
        }
        $limit = max(1, min(49900, $limit));

        return $this->db->select(
            'SELECT id, board_id, title, content, created_at, updated_at FROM ' . $this->db->table('posts')
            . ' WHERE ' . $where . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $limit,
            $params
        );
    }

    /** 게시판의 최신순 일반 글 목록에서 해당 글이 속한 페이지를 구한다. */
    public function pageOf(int $id, int $boardId, int $perPage): int
    {
        $newer = (int) $this->db->selectOne(
            'SELECT COUNT(*) AS c FROM ' . $this->db->table('posts')
            . ' WHERE board_id = :board_id AND deleted_at IS NULL AND is_notice = 0 AND id > :id',
            ['board_id' => $boardId, 'id' => $id]
        )['c'];

        return intdiv($newer, max(1, $perPage)) + 1;
    }

    public function setNotice(int $id, bool $isNotice): void
    {
        $data = ['is_notice' => $isNotice ? 1 : 0];
        // 공지를 내릴 때는 범위도 기본값(board)으로 되돌린다. PostService::noticeFrom() 이
        // '공지 아님' 을 항상 notice_scope='board' 로 저장하는 규칙과 맞춘다. 올릴 때는
        // 이미 정해진 범위를 그대로 둔다 — 이 메서드는 범위를 알지 못한다.
        if (!$isNotice) {
            $data['notice_scope'] = 'board';
        }
        $this->db->update('posts', $data, 'id = :id', ['id' => $id]);
    }

    public function adjustCommentCount(int $id, int $delta): void
    {
        // 0 미만으로 내려가지 않도록 GREATEST 대신 CASE 를 쓴다. 세 DB 공통 문법이다.
        $this->db->execute(
            'UPDATE ' . $this->db->table('posts')
            . ' SET comment_count = CASE WHEN comment_count + ? < 0 THEN 0 ELSE comment_count + ? END'
            . ' WHERE id = ?',
            [$delta, $delta, $id]
        );
    }

    private function escapeLike(string $value): string
    {
        return str_replace(
            [self::LIKE_ESCAPE, '%', '_'],
            [self::LIKE_ESCAPE . self::LIKE_ESCAPE, self::LIKE_ESCAPE . '%', self::LIKE_ESCAPE . '_'],
            $value
        );
    }

    /** 공백은 AND 검색어 구분자다. 같은 단어는 조건을 불필요하게 늘리지 않는다. */
    private function searchTerms(?string $query): array
    {
        if ($query === null || trim($query) === '') {
            return [];
        }

        return array_values(array_unique(preg_split('/\s+/u', trim($query), -1, PREG_SPLIT_NO_EMPTY) ?: []));
    }

    private function hydrate(array $row): array
    {
        $raw = $row['attachments'];
        $row['attachments'] = ($raw === null || $raw === '') ? [] : Json::decode((string) $raw);

        foreach (['id', 'board_id', 'is_notice', 'is_secret', 'view_count', 'comment_count'] as $column) {
            $row[$column] = (int) $row[$column];
        }

        $row['notice_scope'] = ($row['notice_scope'] ?? '') === 'global' ? 'global' : 'board';

        return $row;
    }

    /** 회원 작성자의 현재 프로필 이미지를 한 번의 추가 조회로 붙인다. */
    private function hydrateMany(array $rows): array
    {
        $rows = array_map([$this, 'hydrate'], $rows);
        $ids = [];
        foreach ($rows as $row) {
            $id = (string) ($row['author_id'] ?? '');
            if ($id !== '' && ctype_digit($id) && (int) $id > 0) $ids[(int) $id] = (int) $id;
        }
        $avatars = [];
        if ($ids !== []) {
            $marks = implode(', ', array_fill(0, count($ids), '?'));
            foreach ($this->db->select('SELECT id, avatar_file FROM ' . $this->db->table('users')
                . ' WHERE id IN (' . $marks . ')', array_values($ids)) as $user) {
                $avatars[(string) $user['id']] = $user['avatar_file'];
            }
        }
        foreach ($rows as &$row) $row['author_avatar_file'] = $avatars[(string) ($row['author_id'] ?? '')] ?? null;
        unset($row);
        return $rows;
    }

    private function dehydrate(array $row): array
    {
        if (array_key_exists('attachments', $row) && is_array($row['attachments'])) {
            $row['attachments'] = Json::encode(array_values($row['attachments']));
        }

        foreach (['is_notice', 'is_secret'] as $column) {
            if (array_key_exists($column, $row)) {
                $row[$column] = (int) (bool) $row[$column];
            }
        }

        return $row;
    }
}
