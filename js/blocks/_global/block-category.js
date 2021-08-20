import { getCategories, setCategories } from "@wordpress/blocks";
import MemberPressIcon from "./components/mp-icon";
import "./editor.scss";

setCategories([
  ...getCategories().filter(({ slug }) => slug !== "memberpress"),
  {
    slug: "memberpress",
    title: "MemberPress",
    icon: <MemberPressIcon />
  }
]);
