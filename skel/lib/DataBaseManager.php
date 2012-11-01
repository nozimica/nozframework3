<?php

class ModelManager extends Model {
    private $dbMngr;

    public function __construct($dataManager) {
        parent::__construct($dataManager);
        $this->dbMngr = new DbMngr($this->dataManager);
    }

    public function creaTrabajoAction($params) {
        $outFormats = array('calmet' => 0, 'aermod' => 1);
        $start = new DateTime(sprintf('%s-01-01', $_POST['yearSim']));
        $start->modify('-2 days');
        $end   = new DateTime(sprintf('%s-01-01', $_POST['yearSim'] + 1));
        $this->dbMngr->createJob(array('lat'    => $_POST['latPlace']
                                     , 'lon'    => $_POST['lonPlace']
                                     , 'nameSim'=> $_POST['nameSim']
                                     , 'user'   => $this->authData['usu_username']
                                     , 'format' => $outFormats[$_POST['outFormat']]
                                     , 'start'  => $start->format('d-m-Y')
                                     , 'end'    => $end->format('d-m-Y')));
        return 'Trabajo creado.';
    }

    public function consultaTrabajosAction($params) {
        echo $this->listaAction($params);
    }

    public function inicioAction($params) {
        return $this->mapaAction($params);
    }

    public function mapaAction($params) {
        return $this->authData;
    }

    public function listaAction($params) {
        $data = $this->dbMngr->getJobsByOwner($this->authData['usu_username']);
        /*
        $retStr = "";
        foreach ($data as $row) {
            $creationDate = date('d-m-Y H:i', $row['que_creation']);
            $retStr .= sprintf("%02d: (%d) %s (%.4f; %.4f) [%s; %s]\n"
              , $row['que_id'], $row['que_state'], $creationDate
              , $row['que_lat'], $row['que_lon'], $row['que_dateStart'], $row['que_dateEnd']);
        }*/
        return $data;
    }

    public function dequeueAction($params) {
        return json_encode($this->dbMngr->dequeueJob());
    }

}

class DbMngr {
    private $dbObj = NULL;

    public function __construct($dataManager)
    {
        $this->dbObj = $dataManager;
    }

    public function createSchema()
    {
        $this->dbObj->exec("DROP TABLE IF EXISTS queue");
        $this->dbObj->exec("CREATE TABLE queue(que_id INTEGER PRIMARY KEY AUTOINCREMENT
                                            , que_name TEXT DEFAULT ''
                                            , que_state INTEGER DEFAULT 0
                                            , que_priority INTEGER DEFAULT 0
                                            , que_creation INTEGER
                                            , que_modification INTEGER
                                            , que_username TEXT
                                            , que_lat REAL
                                            , que_lon REAL
                                            , que_dateStart TEXT
                                            , que_dateEnd TEXT)");
    }

    public function createJob($data)
    {
        $creationTime = time();
        $stm = $this->dbObj->prepare("INSERT INTO queue 
                                    (que_id, que_name, que_creation, que_modification, que_username
                                    , que_lat, que_lon, que_dateStart, que_dateEnd, que_outformat)
                                    VALUES (NULL, :name, :creat, :modif, :username, :latVal, :lonVal, :dateS, :dateE, :outformat)");
        $stm->bindParam(':name', $data['nameSim']);
        $stm->bindParam(':creat', $creationTime);
        $stm->bindParam(':modif', $creationTime);
        $stm->bindParam(':username', $data['user']);
        $stm->bindParam(':latVal', $data['lat']);
        $stm->bindParam(':lonVal', $data['lon']);
        $stm->bindParam(':dateS', $data['start']);
        $stm->bindParam(':dateE', $data['end']);
        $stm->bindParam(':outformat', $data['format']);
        $stm->execute();
    }

    public function dequeueJob()
    {
        $countQuery  = "SELECT COUNT(*) FROM queue WHERE que_state = 0 ORDER BY que_priority DESC, que_id ASC";
        $actualQuery = "SELECT *        FROM queue WHERE que_state = 0 ORDER BY que_priority DESC, que_id ASC";
        $this->dbObj->beginTransaction();
        $res = $this->dbObj->query($countQuery);
        if ($res->fetchColumn() == 0) {
            return NULL;
        }
        $stm = $this->dbObj->prepare($actualQuery);
        $stm->execute();
        $dequeuedRow = $stm->fetch();
        $stm = NULL;
        $stm = $this->dbObj->prepare("UPDATE queue SET que_state = 1 WHERE que_id = :deqId");
        $stm->bindParam(':deqId', $dequeuedRow['que_id']);
        $stm->execute();
        $this->dbObj->commit();
        return $dequeuedRow;
    }

    public function getJobsByOwner($user)
    {
        $retArr = array();
        $stm = $this->dbObj->prepare("SELECT * FROM queue where que_username = :user AND que_state = :state");
        $stm->bindParam(':user', $user);
        for ($i = 0; $i <= 2; $i++) {
            $stm->bindParam(':state', $i, PDO::PARAM_INT);
            $stm->execute();
            $retArr[$i] = $stm->fetchAll();
        }
        return $retArr;
    }

    public function __destruct()
    {
        $this->dbObj = NULL;
    }
}
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */
