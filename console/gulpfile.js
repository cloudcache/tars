var gulp = require('gulp'),
    sass = require('gulp-sass'),
    usemin = require('gulp-usemin'),
    rename = require('gulp-rename'),
    minifyCss = require('gulp-minify-css'),
    uglify = require('gulp-uglify'),
    rev = require('gulp-rev');

gulp.task('sass', function(){
  gulp.src('www/assets/scss/*.scss')
  .pipe(sass({errLogToConsole: true}))
  .pipe(gulp.dest('www/assets/css'));
});

gulp.task('watch', function(){
  gulp.watch('www/assets/scss/*.scss', ['sass']);
});

gulp.task('fonts', function(){
  return gulp.src('www/assets/lib/bootstrap/fonts/*')
  .pipe(gulp.dest('www/assets/fonts'));
});

gulp.task('build', ['fonts'], function(){
  gulp.src('src/views/layout.php')
  .pipe(rename({basename: 'layout-dist'}))
  .pipe(usemin({
    assetsDir: 'www',
    // path: './open-pkg/www',
    css:  [minifyCss(), 'concat', rev()],
    css1: [minifyCss(), 'concat', rev()],
    js:   [/*uglify(), */'concat', rev()],
    js1:  [uglify(), rev()]
  }))
  .pipe(rename(function (path) {
    // console.log('rename', path);
    if (path.extname !== '.php') {
      path.dirname = '../../www/' + path.dirname;
    }
  }))
  .pipe(gulp.dest('src/views'));
});

gulp.task('default', ['sass', 'watch']);
