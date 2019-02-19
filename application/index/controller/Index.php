<?php
namespace app\index\controller;

use app\model\Banner;
use app\model\Author;
use app\model\Book;

class Index extends Base
{
    protected $bookService;
    protected function initialize()
    {
        $this->bookService = new \app\service\BookService();
    }

    public function index()
    {
        $banners = cache('banners_homepage');
        if (!$banners){
            $banners = Banner::limit(5)->order('id','desc')->select();
            cache('banners_homepage',$banners);
        }
        $redis = new \Redis();
        $redis->connect(config('site.redis_host'), config('site.redis_port'));
        if (!empty(config('site.redis_auth'))){
            $redis->auth(config('site.redis_auth'));
        }
        $hot_ids = array();
        if (isMobile()){
            $newest = cache('newest_homepage_mobile');
            if (!$newest){
                $newest = $this->bookService->getBooks('create_time');
                cache('newest_homepage_mobile',$newest);
            }
//            $hot = cache('hot_homepage_mobile');
//            if (!$hot){
//                $hot = $this->bookService->getBooks('click');
//                cache('hot_homepage_mobile',$hot);
//            }

            $order = $redis->zRevRange('book_clicks',0,5,true);
            foreach ($order as $key => $value){
                array_push($hot_ids,$key);
            }
            $ends = cache('ends_homepage_mobile');
            if (!$ends){
                $ends = $this->bookService->getBooks('update_time',[['end','=','1']]);
                cache('ends_homepage_mobile',$ends);
            }
        }else{
            $newest = cache('newest_homepage_pc');
            if (!$newest){
                $newest = $this->bookService->getBooks('create_time','1=1',10);
                cache('newest_homepage_pc',$newest);
            }

            $order = $redis->zRevRange('book_clicks',0,9,true);
            foreach ($order as $key => $value){
                array_push($hot_ids,$key);
            }

            $ends = cache('ends_homepage_pc');
            if (!$ends){
                $ends = $this->bookService->getBooks('update_time',[['end','=','1']],10);
                cache('ends',$ends);
            }
        }
        $hot = $this->bookService->getBooksById(implode(',',$hot_ids));
        $rands = $this->bookService->getRandBooks();
        $this->assign([
            'banners' => $banners,
            'banners_count' => count($banners),
            'newest' => $newest,
            'hot' => $hot,
            'ends' => $ends,
            'rands' => $rands
        ]);
        if (!isMobile()){
            $tags = \app\model\Tags::all();
            $this->assign('tags',$tags);
        }

        return view($this->tpl);
    }

    public function search(){
        $keyword = input('keyword');
        $books = cache('searchresult'.$keyword);
        if (!$books){
            $books = $this->bookService->search($keyword);
            cache('searchresult'.$keyword,$books);
        }
        foreach ($books as &$book){
            $author = Author::get($book['author_id']);
            $book['author'] = $author;
        }
        $this->assign([
            'books' => $books,
            'header_title' =>'搜索：'. $keyword,
            'count' => count($books),
        ]);
        return view($this->tpl);
    }

    public function bookshelf(){
        return view($this->tpl);
    }
}

