#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <errno.h>

#include <netinet/in.h>
#include <sys/socket.h>
#include <arpa/inet.h>
#include <sys/epoll.h>
#include <unistd.h>
#include <sys/types.h>
#include <fcntl.h>
#include <map>
#include <string>
#include <vector>
#include <list>

using namespace std;

//g++ chat_thread.cpp -o chat_thread -lpthread

#define IPADDRESS   "127.0.0.1"
//#define IPADDRESS   "192.168.100.210"
#define PORT        8888
#define MAXSIZE     4096
#define LISTENQ     5
#define FDSIZE      1000
#define EPOLLEVENTS 100
#define THREAD_COUNT	10

//函数声明
//创建套接字并进行绑定
static int socket_bind(const char* ip,int port);
//IO多路复用epoll
static void do_epoll(int listenfd);
//事件处理函数
static void handle_events(int epollfd,struct epoll_event *events,int num,int listenfd,char *buf);
//处理接收到的连接
static void handle_accpet(int epollfd,int listenfd);
//读处理
static void do_read(int epollfd,int fd,char *buf);
//写处理
static void do_write(int epollfd,int fd,char *buf);
//添加事件
static void add_event(int epollfd,int fd,int state);
//修改事件
static void modify_event(int epollfd,int fd,int state);
//删除事件
static void delete_event(int epollfd,int fd,int state);
//线程函数
static void *thread_func(void *pIndex);
//设置非阻塞
static int no_blocking(int listenfd);

//消息内存
static map<int, string> mapMsg;
//消息内存锁
static pthread_mutex_t msgMutex;
//线程锁
static pthread_mutex_t mutex[THREAD_COUNT];
//线程条件变量
static pthread_cond_t  cond[THREAD_COUNT];
//是否已经触发了条件变量
static bool signalStatus[THREAD_COUNT] = {false};
//线程处理的数据
static map<unsigned int, list<vector<char> > > mapData;
//线程标志
static pthread_t thread[THREAD_COUNT];

//tcp协议包头
typedef struct 
{
	unsigned int   ip;
	unsigned short protocol;
	unsigned short bodyLen;
} packageHead;

#pragma pack(1)

int main(int argc,char *argv[])
{
	for(int i = 0; i < THREAD_COUNT; ++i)
	{
		pthread_mutex_init(&mutex[i], NULL);
        pthread_cond_init(&cond[i], NULL);
		pthread_create(&thread[i], NULL, thread_func, (void *)i);
	}
	pthread_mutex_init(&msgMutex, NULL);
    int  listenfd = socket_bind(IPADDRESS,PORT);
	no_blocking(listenfd);
    listen(listenfd,LISTENQ);
    do_epoll(listenfd);
    return 0;
}

static void *thread_func(void *pIndex)
{
	unsigned int index = *((int*)(&pIndex));
	for(;;)
	{
		if(mapData[index].empty())
		{
			pthread_mutex_lock(&mutex[index]);
			pthread_cond_wait(&cond[index] ,&mutex[index]);
			signalStatus[index] = false;
			pthread_mutex_unlock(&mutex[index]);
		}
		else
		{
			pthread_mutex_lock(&mutex[index]);
			vector<char> buf(mapData[index].front());
			mapData[index].pop_front();
			pthread_mutex_unlock(&mutex[index]);
			//处理str 4字节fd+请求包
			int fd = 0;
			memcpy(&fd, &buf[0], sizeof(int));
			packageHead head;
			memcpy(&head, &buf[sizeof(int)], sizeof(packageHead));
			//printf("protocol: 0x%04u\n", ntohs(head.protocol));
			unsigned int ip = head.ip;
			pthread_mutex_lock(&msgMutex);
			map<int, string>::iterator iter = mapMsg.find(ip);
			map<int, string>::iterator iterEnd = mapMsg.end();
			size_t msgSize = mapMsg.size();
			pthread_mutex_unlock(&msgMutex);
			switch(ntohs(head.protocol))
			{
				//进入聊天室
				case 0x0001:
				{
					struct in_addr addr;
					memcpy(&addr, &ip, sizeof(ip));
					if(iter == iterEnd)
					{
						packageHead respone;
						respone.protocol = 0x0002;
						respone.protocol = htons(respone.protocol);
						respone.bodyLen = 2;
						respone.bodyLen = htons(respone.bodyLen);
						unsigned short code = 0;
						code = htons(code);
						vector<char> res(sizeof(packageHead)+sizeof(unsigned short), 0);
						memcpy(&res[0], &respone, sizeof(packageHead));
						memcpy(&res[sizeof(packageHead)], &code, sizeof(unsigned short));
						int nwrite = write(fd,&res[0],res.size());
						//进入聊天室消息格式 1.2.3.4 0x0001\n
						string str = inet_ntoa(addr) + string(" 0x0001\n");
						//printf("join: %s\n", str.c_str());
						pthread_mutex_lock(&msgMutex);
						for(iter = mapMsg.begin(); iter != mapMsg.end(); ++iter)
						{
							iter->second += str;
						}
						mapMsg[ip] = "";
						pthread_mutex_unlock(&msgMutex);
					}
					break;
				}
				//离开聊天室
				case 0x0003:
				{
					struct in_addr addr;
					memcpy(&addr, &ip, sizeof(ip));
					if(iter != iterEnd)
					{
						//离开聊天室消息格式 1.2.3.4 0x0003\n
						string str = inet_ntoa(addr) + string(" 0x0003\n");
						//printf("left: %s\n", str.c_str());
						pthread_mutex_lock(&msgMutex);
						mapMsg.erase(ip);
						for(iter = mapMsg.begin(); iter != mapMsg.end(); ++iter)
						{
							iter->second += str;
						}
						pthread_mutex_unlock(&msgMutex);
					}
					break;
				}
				//发送消息
				case 0x0005:
				{
					struct in_addr addr;
					memcpy(&addr, &ip, sizeof(ip));
					if(iter != iterEnd)
					{
						char str[MAXSIZE] = {0};
						memcpy(str, &buf[sizeof(int)+sizeof(packageHead)], ntohs(head.bodyLen));
						//发送消息格式 1.2.3.4|test\n
						string msg = inet_ntoa(addr) + string("|") + str + string("\n");
						//printf("send: %s\n", msg.c_str());
						pthread_mutex_lock(&msgMutex);
						map<int, string>::iterator it;
						for(it = mapMsg.begin(); it != mapMsg.end(); ++it)
						{
							if(it != iter)	//测试接收消息，后续取消注释 wuzx 2015-07-10
							{
								if(it->second.length() >= 1024)
								{
									it->second = "";
								}
								it->second += msg;
							}
						}
						pthread_mutex_unlock(&msgMutex);
					}
					break;
				}
				//接收消息
				case 0x0007:
				{
					if(iter != iterEnd)
					{
						//消息格式 1.2.3.4 0x0001\n1.2.3.4|test\n1.2.3.4 0x0003\n
						//printf("get: %s\n", iter->second.c_str());
						packageHead respone;
						respone.protocol = 0x0008;
						respone.protocol = htons(respone.protocol);
						respone.bodyLen = iter->second.length();
						respone.bodyLen = htons(respone.bodyLen);
						vector<char> res(sizeof(packageHead)+iter->second.length());
						memcpy(&res[0], &respone, sizeof(packageHead));
						memcpy(&res[sizeof(packageHead)], iter->second.c_str(), iter->second.length());
						int nwrite = write(fd,&res[0],res.size());
						iter->second = "";
					}
					break;
				}
				//获取用户ip列表
				case 0x0009:
				{
					if(iter != iterEnd)
					{
						//
						packageHead respone;
						respone.protocol = 0x000A;
						respone.protocol = htons(respone.protocol);
						respone.bodyLen = msgSize*sizeof(unsigned int);
						respone.bodyLen = htons(respone.bodyLen);
						vector<char> res(sizeof(packageHead)+msgSize*sizeof(unsigned int));
						memcpy(&res[0], &respone, sizeof(packageHead));
						int i = 0;
						pthread_mutex_lock(&msgMutex);
						for(iter = mapMsg.begin(); iter != mapMsg.end(); ++iter, ++i)
						{
							memcpy(&res[sizeof(packageHead)+i*sizeof(unsigned int)], &(iter->first), sizeof(unsigned int));
						}
						pthread_mutex_unlock(&msgMutex);
						int nwrite = write(fd,&res[0],res.size());
						//
					}
					break;
				}
			}
			close(fd);
		}
	}
}

static int socket_bind(const char* ip,int port)
{
    int listenfd = socket(AF_INET,SOCK_STREAM,0);
    if (listenfd == -1)
    {
        perror("socket error:");
        exit(1);
    }
	struct sockaddr_in servaddr;
    bzero(&servaddr,sizeof(servaddr));
    servaddr.sin_family = AF_INET;
    inet_pton(AF_INET,ip,&servaddr.sin_addr);
    servaddr.sin_port = htons(port);
    if (bind(listenfd,(struct sockaddr*)&servaddr,sizeof(servaddr)) == -1)
    {
        perror("bind error: ");
        exit(1);
    }
    return listenfd;
}

static int no_blocking(int listenfd)
{
	int flags, s;

	//得到文件状态标志
	flags= fcntl(listenfd, F_GETFL,0);
	if(flags==-1)
	{
		perror("fcntl");
		return-1;
	}

	//设置文件状态标志
	flags |= O_NONBLOCK;
	s= fcntl(listenfd, F_SETFL, flags);
	if(s==-1)
	{
		perror("fcntl");
		return-1;
	}

	return 0;
}

static void do_epoll(int listenfd)
{
    struct epoll_event events[EPOLLEVENTS];
    int ret = 0;
    char buf[MAXSIZE-4] = {0};
    //创建一个描述符
    int epollfd = epoll_create(FDSIZE);
    //添加监听描述符事件
    add_event(epollfd,listenfd,EPOLLIN);
    for (;;)
    {
        //获取已经准备好的描述符事件
		//printf("wait start\n");
        ret = epoll_wait(epollfd,events,EPOLLEVENTS,-1);
		//printf("wait end : %d\n", ret);
        handle_events(epollfd,events,ret,listenfd,buf);
    }
    close(epollfd);
}

static void handle_events(int epollfd,struct epoll_event *events,int num,int listenfd,char *buf)
{
    int fd = 0;
    //进行选好遍历
    for (int i = 0;i < num;++i)
    {
		//printf("i:%d,num:%d,fd:%d,listenfd:%d,events:%d\n",i,num,fd,listenfd,events[i].events);
        fd = events[i].data.fd;
        //根据描述符的类型和事件类型进行处理
        if ((fd == listenfd) &&(events[i].events & EPOLLIN))
		{
            handle_accpet(epollfd,listenfd);
		}
        else if (events[i].events & EPOLLIN)
		{
            do_read(epollfd,fd,buf);
		}
        else
		{
			continue;
		}
    }
}
static void handle_accpet(int epollfd,int listenfd)
{
    struct sockaddr_in cliaddr;
    socklen_t  cliaddrlen = sizeof(struct sockaddr_in);
    int clifd = accept(listenfd,(struct sockaddr*)&cliaddr,&cliaddrlen);
    if (clifd == -1)
        perror("accpet error:");
    else
    {
        //printf("accept a new client: %s:%d\n",inet_ntoa(cliaddr.sin_addr),cliaddr.sin_port);
        //添加一个客户描述符和事件
        add_event(epollfd,clifd,EPOLLIN);
    }
}

static void do_read(int epollfd,int fd,char *buf)
{
    int nread = read(fd,buf,MAXSIZE);
    if (nread == -1)
    {
        perror("read error:");
		close(fd);
    }
    else if (nread == 0)
    {
        fprintf(stderr,"client close.\n");
		close(fd);
    }
    else
    {
		//printf("nread:%d\n",nread);
        //分析数据包
		if(nread < sizeof(packageHead))
		{
			perror("packeage error:");
			close(fd);
		}
		else
		{
			unsigned int ip = 0;
			memcpy(&ip, buf, sizeof(unsigned int));
			int index = ip%THREAD_COUNT;
			//printf("index:%d,mapData[index].size:%d\n",index,mapData[index].size());
			vector<char> str(sizeof(int)+nread, 0);
			memcpy(&str[0], &fd, sizeof(int));
			memcpy(&str[sizeof(int)], buf, nread);
			pthread_mutex_lock(&mutex[index]);
			if(mapData[index].empty() && signalStatus[index] == false)
			{
				pthread_cond_signal(&cond[index]);
				signalStatus[index] == true;
			}
			mapData[index].push_back(str);
			pthread_mutex_unlock(&mutex[index]);
		}
    }
    delete_event(epollfd,fd,EPOLLIN);
}

static void do_write(int epollfd,int fd,char *buf)
{
    int nwrite;
    nwrite = write(fd,buf,strlen(buf));
    if (nwrite == -1)
    {
        perror("write error:");
        close(fd);
        delete_event(epollfd,fd,EPOLLOUT);
    }
    else
        modify_event(epollfd,fd,EPOLLIN);
    memset(buf,0,MAXSIZE);
}

static void add_event(int epollfd,int fd,int state)
{
    struct epoll_event ev;
    ev.events = state;
    ev.data.fd = fd;
    epoll_ctl(epollfd,EPOLL_CTL_ADD,fd,&ev);
}

static void delete_event(int epollfd,int fd,int state)
{
    struct epoll_event ev;
    ev.events = state;
    ev.data.fd = fd;
    epoll_ctl(epollfd,EPOLL_CTL_DEL,fd,&ev);
}

static void modify_event(int epollfd,int fd,int state)
{
    struct epoll_event ev;
    ev.events = state;
    ev.data.fd = fd;
    epoll_ctl(epollfd,EPOLL_CTL_MOD,fd,&ev);
}
