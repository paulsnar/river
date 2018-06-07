begin transaction;

create temp table entries_M as select * from entries;
drop table entries;
drop view recent_entries;

create table entries
(
  _id integer primary key,
  of_feed integer,
  feedwide_id text,
  timestamp integer,
  title text,
  content text,
  link text,
  foreign key (of_feed) references feeds (_id)
);

create index entries_by_timestamp on entries (timestamp);

insert into entries
  select _id, of_feed, feedwide_id, published_at as timestamp, title,
    content, link from entries_M;
drop table entries_M;

create view recent_entries as
  select * from entries order by timestamp desc;

commit;
