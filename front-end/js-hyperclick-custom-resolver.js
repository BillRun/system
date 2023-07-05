"use babel"
/* eslint-disable no-unused-vars */

// If you want to use ES6 module syntax, you will need "use babel" at the top of
// your resolver. Atom will transpile it automatically.
import path from "path"

// spec/fixtures/all-imports.js has some imports that run through this custom
// resolver. I don't know what you might use basedir for, but you have it if you
// need it. In the case of `all-imports.js` basedir would be the absolute path
// to `js-hyperclick/spec/fixtures` without the trailing slash.
export default function customResolver({ basedir, moduleName }) {
  // You can use whatever strategy you want to convert your modules
  const [prefix, ...rest] = moduleName.split("/")

  // Whatever you return will be resolved realative to the file that imported
  // it.
  if (prefix === "this-directory") {
    return "./" + rest.join("/")
  }

  if (prefix === "@") {
    // I think it's probably best to return an absolute path like this most of
    // the time.
    return path.join(__dirname, "src", rest.join("/"))
  }

  // Meteor style imports. These are NOT node compatible because `/` is the root
  // of your filesystem.
  if (moduleName[0] === "/") {
    return path.join(__dirname, moduleName)
  }

  // The module `atom` and node built in modules have custom handling that open
  // a URL. If for some reason you have a module that can't be resolved by
  // normal means, you can also link out to documentation somewhere.
  if (moduleName === "url-example") {
    return "https://atom.io/packages/js-hyperclick"
  }
}
