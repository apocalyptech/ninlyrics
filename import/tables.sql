-- SQL for nin lyrics

drop table if exists p2s;
drop table if exists phrase;
drop table if exists song;
drop table if exists album;

create table album (
    aid int not null auto_increment,
    atitle varchar(100) not null,
    year int not null,
    primary key (aid)
) type=innodb;

create table song (
    sid int not null auto_increment,
    aid int not null,
    stitle varchar(100) not null,
    lyrics text,
    primary key (sid),
    foreign key fk_aid (aid) references album (aid)
) type=innodb;

-- The various "count" fields here are denormalized, but it
-- simplifies our SQL quite a bit, and it's not like this data
-- gets updated outside the bulk import
create table phrase (
    pid int not null auto_increment,
    phrase varchar(100) not null,
    wordcount int not null,
    songcount int not null default 1,
    albumcount int not null default 1,
    primary key (pid),
    unique index idx_phrase (phrase)
) type=innodb;

-- Some denormalization here, but having aid in p2s allows us to
-- skip some JOINs that we'd otherwise have to do when searching
-- by album
create table p2s (
    pid int not null,
    sid int not null,
    aid int not null,
    primary key (pid, sid),
    foreign key fk_pid (pid) references phrase (pid),
    foreign key fk_sid (sid) references song (sid),
    foreign key fk_aid (aid) references album (aid)
) type=innodb;
