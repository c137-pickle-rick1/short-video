import { columnExists } from "./helpers.js";

export const migration002AddAuthorAvatarUrlToTweets = {
  id: "002_add_author_avatar_url_to_tweets",
  run(db) {
    if (columnExists(db, "tweets", "author_avatar_url")) {
      return;
    }

    db.exec("ALTER TABLE tweets ADD COLUMN author_avatar_url TEXT");
  }
};
