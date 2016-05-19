<?php

class CommentSqlDB extends SqlSuper implements CommentDao {

    /**
     * The comment table
     * @var string 
     */
    private $_commentT;

    /**
     * User sqldb to get users
     * @var UserSqlDB 
     */
    private $_userDB;

    /**
     * Vote sqldb to handle vote related functions
     * @var VoteSqlDB
     */
    private $_voteDB;

    public function __construct($connection, $userSqlDb) {
        parent::__construct($connection);
        $this->_userDB = $userSqlDb;
        $this->_voteDB = new VoteSqlDB($connection);
        $this->init();
    }

    private function init() {
        $this->_commentT = Globals::getTableName('comment');
    }

    public function add(DaoObject $comment) {
        if (!$comment instanceof Comment) {
            throw new DBException('The object you tried to add was not a comment object', NULL);
        }
        if (parent::containsId($comment->getId(), 'comment')) {
            throw new DBException('The database already contains a comment with this id', NULL);
        }
        $query = 'INSERT INTO ' . $this->_commentT . ' (`users_writer_id`, `parent_id`, parent_root_id,`commented_on_notif_id`,`comment_txt`, `comment_created`)';
        $query.= 'VALUES (:users_writer_id, :parent_id, :parent_root_id,:commented_on_notif_id, :comment_txt, :comment_created)';
        $statement = parent::prepareStatement($query);
        $queryArgs = array(
            ':users_writer_id' => $comment->getPoster()->getId(),
            ':parent_id' => $comment->getParentId(),
            ':parent_root_id' => $comment->getParentRootId(),
            ':commented_on_notif_id' => $comment->getNotifId(),
            ':comment_txt' => $comment->getBody(),
            ':comment_created' => $comment->getCreatedStr(Globals::getDateTimeFormat('mysql', true))
        );
        $statement->execute($queryArgs);
    }

    public function get($id) {
        parent::triggerIdNotFound($id, 'comment');
        $query = 'SELECT * FROM ' . $this->_commentT . ' WHERE comment_id=?';
        $statement = parent::prepareStatement($query);
        $statement->bindParam(1, $id);
        $statement->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);
        $result = $statement->fetchAll();
        $row = $result[0];
        $poster = $this->_userDB->getSimple($row['users_writer_id']);
        $voters = $this->_userDB->getUserDistDB()->getVoters($row['comment_id']);
        $comment = parent::getCreationHelper()->createComment($row, $poster, $voters);
        return $comment;
    }

    public function getByString($identifier) {
        $query = 'SELECT comment_id FROM ' . $this->_commentT . ' WHERE comment_txt = :identifier';
        $statement = parent::prepareStatement($query);
        $statement->bindParam(':identifier', $identifier);
        $statement->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);
        $result = $statement->fetchAll();
        if (empty($result)) {
            throw new DBException('No comment with this body: ' . $identifier);
        }
        $row = $result[0];
        return $this->get($row['comment_id']);
    }

    public function remove($id) {
        parent::triggerIdNotFound($id, 'comment');
        $query = 'DELETE FROM ' . $this->_commentT . ' WHERE comment_id=?';
        $statement = parent::prepareStatement($query);
        $statement->bindParam(1, $id);
        $statement->execute();
    }

    public function updateCommentText($commentId, $text) {
        parent::triggerIdNotFound($commentId, 'comment');
        $query = 'UPDATE ' . $this->_commentT . ' SET comment_txt = :text WHERE comment_id = :commentId';
        $statement = parent::prepareStatement($query);
        $queryArgs = array(
            ':text' => $text,
            ':commentId' => $commentId
        );
        $statement->execute($queryArgs);
    }

//    public function getParentId($subId) {
//        parent::triggerIdNotFound($id, 'comment');
//        $query = '';
//        $statement = parent::prepareStatement($query);
//        $queryArgs = array(
//        );
//        $statement->execute($queryArgs);
//    }

    public function getSubComments($parentId, $limit) {
        parent::triggerIdNotFound($parentId, 'comment');
        $idCol = 'parent_id';
        $query = 'SELECT * FROM ' . $this->_commentT . ' WHERE ' . $idCol . '= ? ORDER BY comment_created DESC LIMIT ?';
        $statement = parent::prepareStatement($query);
        $statement->bindParam(1, $parentId);
        $statement->bindParam(2, $limit, PDO::PARAM_INT);
        $statement->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);
        $result = $statement->fetchAll();

        $subComments = array();
        foreach ($result as $row) {
            $poster = $this->_userDB->getSimple($row['users_writer_id']);
            $voters = $this->_userDB->getUserDistDB()->getVoters($row['comment_id']);
            $comment = parent::getCreationHelper()->createComment($row, $poster, $voters);
            array_push($subComments, $comment);
        }
        return $subComments;
    }

    public function getSubCommentsCount($parentId) {
        parent::triggerIdNotFound($parentId, 'comment');
        $idCol = 'parent_id';
        $query = 'SELECT COUNT(*) as count FROM ' . $this->_commentT . ' WHERE ' . $idCol . '= ?';
        $statement = parent::prepareStatement($query);
        $statement->bindParam(1, $parentId);
        $statement->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);
        $result = $statement->fetchAll();
        return $result[0]['count'];
    }

    /**
     * getReviewRootcomments
     * Returns all root comments for the review with this id
     * 
     * START ORIGINAL SQL
      SELECT c.comment_id, c.users_writer_id, c.parent_id, c.parent_root_id, c.commented_on_notif_id, c.comment_txt, c.comment_created
      FROM
      comments c LEFT JOIN reviews_has_comments r
      ON c.comment_id = r.comments_comment_id
      WHERE r.reviews_review_id = 1
      ORDER BY c.comment_created DESC
     * END ORIGINAL SQL
     * 
     * @param int $reviewId
     * @return Review[]
     * @throws DBException
     */
    public function getReviewRootComments($reviewId) {
        //FIXME getRootComments($objectname, $objectId,..)
        parent::triggerIdNotFound($reviewId, 'review');
        $query = 'SELECT c.comment_id, c.users_writer_id, c.parent_id, c.parent_root_id, c.commented_on_notif_id, c.comment_txt, c.comment_created ';
        $query .= 'FROM ' . $this->_commentT . ' c LEFT JOIN ' . Globals::getTableName('review_comment') . ' r ';
        $query .= 'ON c.comment_id = r.comments_comment_id ';
        $query .= 'WHERE r.reviews_review_id = ? ';
        $query .= 'ORDER BY c.comment_created DESC';
        $statement = parent::prepareStatement($query);
        $statement->bindParam(1, $reviewId);
        $statement->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);
        $result = $statement->fetchAll();
        if (!empty($result)) {
            $comments = array();
            foreach ($result as $row) {
                $poster = $this->_userDB->getSimple($row['users_writer_id']);
                $voters = $this->_userDB->getUserDistDB()->getVoters($row['comment_id']);
                $comment = parent::getCreationHelper()->createComment($row, $poster, $voters);
                array_push($comments, $comment);
            }
            return $comments;
        } else {
            return NULL;
        }
    }

    /**
     * getVideoRootComments
     * Returns all root comments for the video with this id
     * 
     * START ORIGINAL SQL
      SELECT c.comment_id, c.users_writer_id, c.parent_id, c.parent_root_id, c.commented_on_notif_id, c.comment_txt, c.comment_created
      FROM
      comments c LEFT JOIN video_has_comments v
      ON c.comment_id = v.comments_comment_id
      WHERE v.video_video_id = 1
      ORDER BY c.comment_created DESC
     * END ORIGINAL SQL
     * 
     * @param int $videoId
     * @return Review[]
     * @throws DBException
     */
    public function getVideoRootComments($videoId) {
        //FIXME getRootComments($objectname, $objectId,..)
        parent::triggerIdNotFound($videoId, 'video');
        $query = 'SELECT c.comment_id, c.users_writer_id, c.parent_id, c.parent_root_id, c.commented_on_notif_id, c.comment_txt, c.comment_created ';
        $query .= 'FROM ' . $this->_commentT . ' c LEFT JOIN ' . Globals::getTableName('video_comment') . ' v ';
        $query .= 'ON c.comment_id = v.comments_comment_id ';
        $query .= 'WHERE v.video_video_id = ? ';
        $query .= 'ORDER BY c.comment_created DESC';
        $statement = parent::prepareStatement($query);
        $statement->bindParam(1, $videoId);
        $statement->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);
        $result = $statement->fetchAll();
        if (!empty($result)) {
            $comments = array();
            foreach ($result as $row) {
                $poster = $this->_userDB->getSimple($row['users_writer_id']);
                $voters = $this->_userDB->getUserDistDB()->getVoters($row['comment_id']);
                $comment = parent::getCreationHelper()->createComment($row, $poster, $voters);
                array_push($comments, $comment);
            }
            return $comments;
        } else {
            return NULL;
        }
    }

    public function addVoter($commentId, $voterId, $notifId, $voteFlag) {
        $this->_voteDB->addVoter('comment', $commentId, $voterId, $notifId, $voteFlag);
    }

    public function updateVoter($commentId, $voterId, $voteFlag) {
        $this->_voteDB->updateVoter('comment', $commentId, $voterId, $voteFlag);
    }

    public function removeVoter($commentId, $voterId) {
        $this->_voteDB->removeVoter('comment', $commentId, $voterId);
    }

    public function getVotedNotifId($commentId, $voteFlag) {
        return $this->_voteDB->getVotedNotifId('comment', $commentId, $voteFlag);
    }

}
