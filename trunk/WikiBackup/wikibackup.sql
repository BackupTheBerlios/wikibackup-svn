CREATE TABLE /*$wgDBprefix*/backups (
	backup_jobid bigint (20)    unsigned  NOT NULL  UNIQUE  PRIMARY KEY AUTO_INCREMENT,
	status       varchar(10)              NOT NULL,
	timestamp    int    (20)    unsigned  NOT NULL  UNIQUE,
	userid       int    (10)    unsigned  NOT NULL,
	FOREIGN KEY (userid) references /*$wgDBprefix*/user(user_id)
);

CREATE UNIQUE INDEX IDX_JOBID
ON /*$wgDBprefix*/backups (backup_jobid);

CREATE UNIQUE INDEX IDX_TIME
ON /*$wgDBprefix*/backups (timestamp);

ALTER TABLE /*$wgDBprefix*/user
ADD COLUMN user_lastbackup varchar(30);
